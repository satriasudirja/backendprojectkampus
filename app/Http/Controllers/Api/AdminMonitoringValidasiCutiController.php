<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegCutiRecord;
use App\Models\SimpegDaftarCuti;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegJenisKehadiran;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminMonitoringValidasiCutiController extends Controller
{
    /**
     * Menampilkan daftar pengajuan cuti atau pegawai yang belum mengajukan.
     */
    public function index(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized - Silakan login terlebih dahulu'], 401);
            }

            if ($request->status === 'belum_diajukan') {
                return $this->getPegawaiBelumMengajukan($request);
            }
            
            $perPage = $request->per_page ?? 10;
            $search = $request->search;
            $unitKerjaFilter = $request->unit_kerja;
            $periodeCutiFilter = $request->periode_cuti;
            $statusFilter = $request->status;
            $jenisCutiFilter = $request->jenis_cuti;

            $query = SimpegCutiRecord::with(['pegawai.unitKerja', 'pegawai.jabatanFungsional', 'jenisCuti', 'approver']);

            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            if ($jenisCutiFilter && $jenisCutiFilter !== 'semua') {
                $query->where('jenis_cuti_id', $jenisCutiFilter);
            }

            if ($periodeCutiFilter && $periodeCutiFilter !== 'semua') {
                $this->applyPeriodeCutiFilter($query, $periodeCutiFilter);
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $statusMap = ['diajukan' => 'diajukan', 'dibatalkan' => 'ditolak', 'disetujui' => 'disetujui'];
                if (isset($statusMap[$statusFilter])) {
                    $query->where('status_pengajuan', $statusMap[$statusFilter]);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $likeOperator = (config('database.connections.' . config('database.default') . '.driver') === 'pgsql') ? 'ilike' : 'like';
                    $q->where('no_urut_cuti', $likeOperator, '%'.$search.'%')
                        ->orWhere('alasan_cuti', $likeOperator, '%'.$search.'%')
                        ->orWhereHas('pegawai', function($subQ) use ($search, $likeOperator) {
                            $subQ->where('nip', $likeOperator, '%'.$search.'%')->orWhere('nama', $likeOperator, '%'.$search.'%');
                        })
                        ->orWhereHas('jenisCuti', function($subQ) use ($search, $likeOperator) {
                            $subQ->where('nama_jenis_cuti', $likeOperator, '%'.$search.'%');
                        });
                });
            }

            $query->orderBy('created_at', 'desc');
            $pengajuanCuti = $query->paginate($perPage);

            return $this->formatResponsePengajuan($pengajuanCuti, $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Menampilkan detail satu pengajuan cuti.
     */
    public function show($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['success' => false, 'message' => 'ID pengajuan tidak valid.'], 400);
        }

        try {
            $pengajuanCuti = SimpegCutiRecord::with([
                'pegawai.unitKerja', 
                'pegawai.statusAktif', 
                'pegawai.jabatanFungsional',
                'jenisCuti', 
                'approver'
            ])->find($id);

            if (!$pengajuanCuti) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan cuti tidak ditemukan'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPengajuanCuti($pengajuanCuti, true),
                'timeline' => $this->getTimelinePengajuan($pengajuanCuti)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Menyetujui satu pengajuan cuti.
     */
    public function approvePengajuan(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return response()->json(['success' => false, 'message' => 'ID pengajuan tidak valid.'], 400);
        }
        
        $validator = Validator::make($request->all(), ['keterangan_admin' => 'nullable|string|max:500']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanCuti = SimpegCutiRecord::find($id);
            if (!$pengajuanCuti) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Data pengajuan cuti tidak ditemukan'], 404);
            }
            
            if (!in_array($pengajuanCuti->status_pengajuan, ['diajukan', 'ditolak'])) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" atau "dibatalkan" yang dapat disetujui'], 422);
            }

            $oldData = $pengajuanCuti->getOriginal();
            
            $pengajuanCuti->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'tgl_ditolak' => null,
            ]);
            
            $this->createAbsensiForCuti($pengajuanCuti, $request->keterangan_admin);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('approve', $pengajuanCuti, $oldData);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil disetujui',
                'data' => $this->formatPengajuanCuti($pengajuanCuti->fresh(['pegawai.unitKerja', 'jenisCuti', 'approver']), true)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui pengajuan: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Membatalkan/menolak satu pengajuan cuti.
     */
    public function rejectPengajuan(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return response()->json(['success' => false, 'message' => 'ID pengajuan tidak valid.'], 400);
        }
        
        $validator = Validator::make($request->all(), ['keterangan_admin' => 'required|string|max:500']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanCuti = SimpegCutiRecord::find($id);
            if (!$pengajuanCuti) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan cuti tidak ditemukan'], 404);
            }

            if (!in_array($pengajuanCuti->status_pengajuan, ['diajukan', 'disetujui'])) {
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" atau "disetujui" yang dapat dibatalkan'], 422);
            }
            
            if ($pengajuanCuti->status_pengajuan === 'disetujui') {
                $this->deleteAbsensiForCuti($pengajuanCuti);
            }

            $oldData = $pengajuanCuti->getOriginal();
            
            $pengajuanCuti->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'tgl_disetujui' => null, 
            ]);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('reject', $pengajuanCuti, $oldData);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil dibatalkan',
                'data' => $this->formatPengajuanCuti($pengajuanCuti->fresh(['pegawai.unitKerja', 'jenisCuti', 'approver']), true)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal membatalkan pengajuan: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Menyetujui beberapa pengajuan cuti sekaligus (batch).
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid',
            'keterangan_admin' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanList = SimpegCutiRecord::whereIn('id', $request->ids)
                ->whereIn('status_pengajuan', ['diajukan', 'ditolak'])
                ->get();

            if($pengajuanList->isEmpty()){
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada pengajuan yang valid untuk disetujui.'], 404);
            }

            foreach ($pengajuanList as $pengajuan) {
                $this->createAbsensiForCuti($pengajuan, $request->keterangan_admin);
                    if (class_exists(ActivityLogger::class)) {
                        ActivityLogger::log('approve', $pengajuan, $pengajuan->getOriginal());
                }
            }
            
            $updatedCount = SimpegCutiRecord::whereIn('id', $pengajuanList->pluck('id'))->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'tgl_ditolak' => null,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => "Berhasil menyetujui {$updatedCount} pengajuan cuti", 'updated_count' => $updatedCount]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal batch approve: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Membatalkan beberapa pengajuan cuti sekaligus (batch).
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid',
            'keterangan_admin' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanList = SimpegCutiRecord::whereIn('id', $request->ids)
                ->whereIn('status_pengajuan', ['diajukan', 'disetujui'])
                ->get();
            
            if($pengajuanList->isEmpty()){
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada pengajuan yang valid untuk dibatalkan.'], 404);
            }

            foreach ($pengajuanList as $pengajuan) {
                if ($pengajuan->status_pengajuan === 'disetujui') {
                    $this->deleteAbsensiForCuti($pengajuan);
                }
                if (class_exists(ActivityLogger::class)) {
                    ActivityLogger::log('reject', $pengajuan, $pengajuan->getOriginal());
                }
            }
            
            $updatedCount = SimpegCutiRecord::whereIn('id', $pengajuanList->pluck('id'))->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'tgl_disetujui' => null,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => "Berhasil membatalkan {$updatedCount} pengajuan cuti", 'updated_count' => $updatedCount]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal batch reject: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }
    
    // =========================================================
    // PRIVATE HELPER METHODS
    // =========================================================

    private function getPegawaiBelumMengajukan(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaFilter = $request->unit_kerja;
        $periodeCutiFilter = $request->periode_cuti;

        $query = SimpegPegawai::with(['unitKerja', 'jabatanFungsional', 'statusAktif'])->whereRaw('LOWER(status_kerja) = ?', ['aktif']);

        if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
            $query->where('unit_kerja_id', $unitKerjaFilter);
        }

        $query->whereHas('role', function($q) {
            $q->where('nama', 'Dosen');
        });

        if ($search) {
            $query->where(function($q) use ($search) {
                $likeOperator = (config('database.connections.' . config('database.default') . '.driver') === 'pgsql') ? 'ilike' : 'like';
                $q->where('nip', $likeOperator, '%'.$search.'%')
                    ->orWhere('nama', $likeOperator, '%'.$search.'%')
                    ->orWhereHas('unitKerja', function($subQ) use ($search, $likeOperator) {
                        $subQ->where('nama_unit', $likeOperator, '%'.$search.'%');
                    });
            });
        }

        $pegawaiIds = $query->pluck('id');

        if ($periodeCutiFilter && $periodeCutiFilter !== 'semua') {
            $sudahMengajukan = $this->getPegawaiSudahMengajukan($periodeCutiFilter);
            $pegawaiIds = collect($pegawaiIds)->diff($sudahMengajukan);
        }

        $pegawaiBelumMengajukan = $query->whereIn('id', $pegawaiIds)
            ->orderBy('nama', 'asc')
            ->paginate($request->per_page ?? 10);
        
        return $this->formatResponseBelumMengajukan($pegawaiBelumMengajukan);
    }

    private function createAbsensiForCuti(SimpegCutiRecord $pengajuan, $keteranganAdmin = null)
    {
        $pengajuan->loadMissing('pegawai', 'jenisCuti');

        if(!$pengajuan->pegawai) {
            throw new \Exception("Data pegawai tidak ditemukan untuk pengajuan cuti ID: " . $pengajuan->id);
        }

        $jenisKehadiranCuti = SimpegJenisKehadiran::where('kode_jenis', 'C')->first();
        if (!$jenisKehadiranCuti) {
            throw new \Exception("Jenis Kehadiran 'Cuti' dengan kode 'C' tidak ditemukan. Mohon konfigurasi sistem.");
        }

        $period = CarbonPeriod::create($pengajuan->tgl_mulai, $pengajuan->tgl_selesai);
        $user = Auth::user()->pegawai;

        foreach ($period as $date) {
            $tanggalAbsensiStr = $date->format('Y-m-d');
            
            // PERBAIKAN: Gunakan keterangan dari admin jika ada, jika tidak, buat otomatis
            $keteranganFinal = $keteranganAdmin;
            if (empty($keteranganFinal)) {
                $keteranganFinal = sprintf(
                    'Cuti disetujui secara otomatis untuk: %s. Disetujui oleh: %s.',
                    $pengajuan->jenisCuti->nama_jenis_cuti ?? 'N/A',
                    $user->nama ?? 'Sistem'
                );
            }
            
            $attributes = [
                'pegawai_id' => $pengajuan->pegawai_id,
                'tanggal_absensi' => $tanggalAbsensiStr,
                'jenis_kehadiran_id' => $jenisKehadiranCuti->id,
                'cuti_record_id' => $pengajuan->id,
                'izin_record_id' => null,
                'check_sum_absensi' => md5($pengajuan->pegawai_id . $tanggalAbsensiStr . 'cuti' . $pengajuan->id),
                'status_verifikasi' => 'verified',
                'verifikasi_oleh' => $user->nama,
                'verifikasi_at' => now(),
                'keterangan' => $keteranganFinal,
                'jam_masuk' => null, 'jam_keluar' => null, 'jam_kerja_id' => null, 'setting_kehadiran_id' => null,
                'latitude_masuk' => null, 'longitude_masuk' => null, 'lokasi_masuk' => null, 'foto_masuk' => null,
                'latitude_keluar' => null, 'longitude_keluar' => null, 'lokasi_keluar' => null, 'foto_keluar' => null,
                'rencana_kegiatan' => null, 'realisasi_kegiatan' => null,
                'durasi_kerja' => 0, 'durasi_terlambat' => 0, 'durasi_pulang_awal' => 0,
                'terlambat' => false, 'pulang_awal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
    
            DB::table('simpeg_absensi_record')->updateOrInsert(
                ['pegawai_id' => $pengajuan->pegawai_id, 'tanggal_absensi' => $tanggalAbsensiStr],
                $attributes
            );
        }
    }

    private function deleteAbsensiForCuti(SimpegCutiRecord $pengajuan)
    {
        SimpegAbsensiRecord::where('cuti_record_id', $pengajuan->id)->delete();
    }
    
    private function getPegawaiSudahMengajukan($periodeCuti)
    {
        $query = SimpegCutiRecord::select('pegawai_id');
        $this->applyPeriodeCutiFilter($query, $periodeCuti);
        return $query->distinct()->pluck('pegawai_id');
    }

    private function applyPeriodeCutiFilter($query, $periodeCuti)
    {
        $currentYear = Carbon::now()->year;
        switch ($periodeCuti) {
            case '01': $query->whereYear('tgl_mulai', $currentYear); break;
            case '02': $query->whereYear('tgl_mulai', $currentYear)->whereMonth('tgl_mulai', '<=', 6); break;
            case '03': $query->whereYear('tgl_mulai', $currentYear)->whereMonth('tgl_mulai', '>', 6); break;
            case '04': $query->whereYear('tgl_mulai', $currentYear)->whereMonth('tgl_mulai', '<=', 3); break;
            case '05': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-04-01", "{$currentYear}-06-30"]); break;
            case '06': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-07-01", "{$currentYear}-09-30"]); break;
            case '07': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-10-01", "{$currentYear}-12-31"]); break;
        }
    }

    private function formatResponsePengajuan(LengthAwarePaginator $paginator, Request $request)
    {
        $batchActions = [];
        if (in_array($request->status, ['diajukan', 'ditolak'])) {
            $batchActions = [
                'approve' => ['url' => url('/api/admin/validasi-cuti/batch-approve'), 'method' => 'POST', 'label' => 'Setujui Terpilih', 'color' => 'success'],
            ];
        }
        if (in_array($request->status, ['diajukan', 'disetujui'])) {
             $batchActions['reject'] = ['url' => url('/api/admin/validasi-cuti/batch-reject'), 'method' => 'POST', 'label' => 'Batalkan Terpilih', 'color' => 'danger'];
        }

        return response()->json([
            'success' => true,
            'data' => $paginator->map(fn($item) => $this->formatPengajuanCuti($item, true))->filter()->values(),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(),
                'total' => $paginator->total(), 'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()
            ],
            'batch_actions' => $batchActions
        ]);
    }

    private function formatResponseBelumMengajukan(LengthAwarePaginator $pegawaiPaginator)
    {
        return response()->json([
            'success' => true,
            'data' => $pegawaiPaginator->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nip' => $item->nip ?? '-',
                    'nama_pegawai' => ($item->gelar_depan ? $item->gelar_depan . ' ' : '') . $item->nama . ($item->gelar_belakang ? ', ' . $item->gelar_belakang : ''),
                    'unit_kerja' => $this->getUnitKerjaNama($item),
                    'jenis_cuti' => '-', 'keperluan' => '-', 'lama_cuti' => '-',
                    'status' => 'Belum Diajukan', 'tgl_input' => '-',
                    'actions' => [
                        'remind' => ['url' => url("/api/admin/validasi-cuti/remind/{$item->id}"), 'method' => 'POST', 'label' => 'Ingatkan', 'icon' => 'bell', 'color' => 'warning'],
                        'create' => ['url' => url("/api/admin/validasi-cuti/create-for-pegawai/{$item->id}"), 'method' => 'GET', 'label' => 'Buatkan Cuti', 'icon' => 'plus-circle', 'color' => 'primary']
                    ]
                ];
            }),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $pegawaiPaginator->currentPage(), 
                'per_page' => $pegawaiPaginator->perPage(),
                'total' => $pegawaiPaginator->total(), 
                'last_page' => $pegawaiPaginator->lastPage(),
                'from' => $pegawaiPaginator->firstItem(), 
                'to' => $pegawaiPaginator->lastItem()
            ]
        ]);
    }

    private function formatPengajuanCuti($pengajuan, $includeActions = false)
    {
        if (!$pengajuan || !$pengajuan->pegawai) {
            return null;
        }

        $pegawai = $pengajuan->pegawai;
        $status = $pengajuan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        $data = [
            'id' => $pengajuan->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_pegawai' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $this->getUnitKerjaNama($pegawai),
            'jenis_cuti' => $pengajuan->jenisCuti->nama_jenis_cuti ?? '-',
            'keperluan' => $pengajuan->alasan_cuti ?? '-',
            'lama_cuti' => $pengajuan->jumlah_cuti . ' hari',
            'status' => $statusInfo['label'],
            'status_info' => $statusInfo,
            'tgl_input' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d-m-Y') : ($pengajuan->created_at ? $pengajuan->created_at->format('d-m-Y') : '-'),
            'detail_data' => [
                'no_urut_cuti' => $pengajuan->no_urut_cuti,
                'tgl_mulai' => $pengajuan->tgl_mulai ? Carbon::parse($pengajuan->tgl_mulai)->format('d M Y') : '-',
                'tgl_selesai' => $pengajuan->tgl_selesai ? Carbon::parse($pengajuan->tgl_selesai)->format('d M Y') : '-',
                'alamat' => $pengajuan->alamat,
                'no_telp' => $pengajuan->no_telp,
                'file_cuti' => $pengajuan->file_cuti ? [
                    'nama_file' => basename($pengajuan->file_cuti), 
                    'url' => Storage::disk('public')->url('pegawai/cuti/' . $pengajuan->file_cuti)
                ] : null,
                'tgl_diajukan' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d M Y H:i:s') : '-',
                'tgl_disetujui' => $pengajuan->tgl_disetujui ? Carbon::parse($pengajuan->tgl_disetujui)->format('d M Y H:i:s') : '-',
                'tgl_ditolak' => $pengajuan->tgl_ditolak ? Carbon::parse($pengajuan->tgl_ditolak)->format('d M Y H:i:s') : '-',
                'approved_by_name' => $pengajuan->approver->nama ?? '-',
            ]
        ];

        if ($includeActions) {
            $data['actions'] = [];
            if (in_array($status, ['diajukan', 'ditolak'])) {
                $data['actions']['approve'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}/approve"), 'method' => 'PATCH', 'label' => 'Setujui', 'icon' => 'check', 'color' => 'success'];
            }
            if (in_array($status, ['diajukan', 'disetujui'])) {
                $data['actions']['reject'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}/reject"), 'method' => 'PATCH', 'label' => 'Batalkan', 'icon' => 'x', 'color' => 'danger'];
            }
            $data['actions']['view'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}"), 'method' => 'GET', 'label' => 'Lihat Detail', 'icon' => 'eye', 'color' => 'info'];
        }
        return $data;
    }

    private function getStatusInfo($status)
    {
        $statusMap = [
            'draft' => ['label' => 'Draft', 'color' => 'secondary', 'icon' => 'edit'],
            'diajukan' => ['label' => 'Diajukan', 'color' => 'info', 'icon' => 'clock'],
            'disetujui' => ['label' => 'Disetujui', 'color' => 'success', 'icon' => 'check-circle'],
            'ditolak' => ['label' => 'Dibatalkan', 'color' => 'danger', 'icon' => 'x-circle']
        ];
        return $statusMap[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary', 'icon' => 'circle'];
    }

    private function getTimelinePengajuan($pengajuan)
    {
        $timeline = [];
        if ($pengajuan->created_at) {
            $timeline[] = ['status' => 'draft', 'label' => 'Dibuat', 'tanggal' => $pengajuan->created_at->format('d-m-Y H:i'), 'is_completed' => true];
        }
        if ($pengajuan->tgl_diajukan) {
            $timeline[] = ['status' => 'diajukan', 'label' => 'Diajukan', 'tanggal' => Carbon::parse($pengajuan->tgl_diajukan)->format('d-m-Y H:i'), 'is_completed' => true];
        }
        if ($pengajuan->tgl_disetujui && $pengajuan->status_pengajuan === 'disetujui') {
            $timeline[] = ['status' => 'disetujui', 'label' => 'Disetujui', 'tanggal' => Carbon::parse($pengajuan->tgl_disetujui)->format('d M Y H:i'), 'is_completed' => true];
        }
        if ($pengajuan->tgl_ditolak && $pengajuan->status_pengajuan === 'ditolak') {
            $timeline[] = ['status' => 'ditolak', 'label' => 'Dibatalkan', 'tanggal' => Carbon::parse($pengajuan->tgl_ditolak)->format('d M Y H:i'), 'is_completed' => true];
        }
        return $timeline;
    }

    private function getFilterOptions()
    {
        return [
            'unit_kerja' => SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit')->get()->map(fn($u) => ['id' => $u->id, 'value' => $u->id, 'label' => $u->nama_unit])->prepend(['id' => 'semua', 'value' => 'semua', 'label' => 'Semua Unit Kerja']),
            'periode_cuti' => [['value' => 'semua', 'label' => 'Semua Periode'], ['value' => '01', 'label' => 'Cuti Tahunan'], ['value' => '02', 'label' => 'Semester 1'], ['value' => '03', 'label' => 'Semester 2'], ['value' => '04', 'label' => 'Kuartal 1'], ['value' => '05', 'label' => 'Kuartal 2'], ['value' => '06', 'label' => 'Kuartal 3'], ['value' => '07', 'label' => 'Kuartal 4']],
            'status' => [['value' => 'semua', 'label' => 'Semua Status'], ['value' => 'belum_diajukan', 'label' => 'Belum Diajukan'], ['value' => 'diajukan', 'label' => 'Diajukan'], ['value' => 'disetujui', 'label' => 'Disetujui'], ['value' => 'dibatalkan', 'label' => 'Dibatalkan']],
            'jenis_cuti' => SimpegDaftarCuti::select('id', 'nama_jenis_cuti')->orderBy('nama_jenis_cuti')->get()->map(fn($j) => ['id' => $j->id, 'value' => $j->id, 'label' => $j->nama_jenis_cuti])->prepend(['id' => 'semua', 'value' => 'semua', 'label' => 'Semua Jenis Cuti']),
        ];
    }
    
    private function getUnitKerjaNama($pegawai)
    {
        if (!$pegawai) {
            return '-';
        }

        if ($pegawai->relationLoaded('unitKerja') && $pegawai->unitKerja) {
            return $pegawai->unitKerja->nama_unit;
        }

        if ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            return $unitKerja ? $unitKerja->nama_unit : '-';
        }

        return '-';
    }
}
