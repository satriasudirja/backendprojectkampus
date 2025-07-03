<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPengajuanIzinDosen;
use App\Models\SimpegJenisIzin;
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

class AdminMonitoringValidasiIzinController extends Controller
{
    // =========================================================
    // PUBLIC API ENDPOINTS
    // =========================================================

    /**
     * Monitoring validasi pengajuan izin untuk admin.
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
            $periodeIzinFilter = $request->periode_izin;
            $statusFilter = $request->status;
            $jenisIzinFilter = $request->jenis_izin;

            $query = SimpegPengajuanIzinDosen::with([
                'pegawai.unitKerja',
                'pegawai.jabatanAkademik',
                'jenisIzin',
                'approver'
            ]);

            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            if ($jenisIzinFilter && $jenisIzinFilter !== 'semua') {
                $query->where('jenis_izin_id', $jenisIzinFilter);
            }

            if ($periodeIzinFilter) {
                $this->applyPeriodeIzinFilter($query, $periodeIzinFilter);
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $statusMap = [
                    'diajukan' => 'diajukan',
                    'dibatalkan' => 'ditolak',
                    'disetujui' => 'disetujui'
                ];
                
                if (isset($statusMap[$statusFilter])) {
                    $query->where('status_pengajuan', $statusMap[$statusFilter]);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $likeOperator = (config('database.connections.' . config('database.default') . '.driver') === 'pgsql') ? 'ilike' : 'like';
                    $q->where('no_izin', $likeOperator, '%'.$search.'%')
                      ->orWhere('alasan_izin', $likeOperator, '%'.$search.'%')
                      ->orWhereHas('pegawai', function($subQ) use ($search, $likeOperator) {
                          $subQ->where('nip', $likeOperator, '%'.$search.'%')
                               ->orWhere('nama', $likeOperator, '%'.$search.'%');
                      })
                      ->orWhereHas('jenisIzin', function($subQ) use ($search, $likeOperator) {
                          $subQ->where('jenis_izin', $likeOperator, '%'.$search.'%');
                      });
                });
            }

            $query->orderBy('tgl_diajukan', 'desc')->orderBy('created_at', 'desc');
            $pengajuanIzin = $query->paginate($perPage);

            return $this->formatResponsePengajuan($pengajuanIzin, $request);

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
     * Get detail pengajuan izin
     */
    public function show($id)
    {
        try {
            $pengajuanIzin = SimpegPengajuanIzinDosen::with([
                'pegawai.unitKerja',
                'pegawai.statusAktif', 
                'pegawai.jabatanAkademik',
                'jenisIzin',
                'approver'
            ])->find($id);

            if (!$pengajuanIzin) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan izin tidak ditemukan'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPengajuanIzin($pengajuanIzin, true),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Setujui pengajuan izin
     */
    public function approvePengajuan(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $pengajuanIzin = SimpegPengajuanIzinDosen::find($id);

            if (!$pengajuanIzin) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan izin tidak ditemukan'], 404);
            }

            if (!in_array($pengajuanIzin->status_pengajuan, ['diajukan', 'ditolak'])) {
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" atau "ditolak" yang dapat disetujui'], 422);
            }

            $oldData = $pengajuanIzin->getOriginal();

            $pengajuanIzin->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'approved_by' => Auth::user()->nama,
                'keterangan' => $request->keterangan,
                'tgl_ditolak' => null
            ]);
            
            $this->createAbsensiForIzin($pengajuanIzin, $request->keterangan);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('approve', $pengajuanIzin, $oldData);
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil disetujui',
                'data' => $this->formatPengajuanIzin($pengajuanIzin->fresh(['pegawai.unitKerja', 'jenisIzin', 'approver']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui pengajuan: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Tolak/Batalkan pengajuan izin
     */
    public function rejectPengajuan(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'keterangan' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $pengajuanIzin = SimpegPengajuanIzinDosen::find($id);

            if (!$pengajuanIzin) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan izin tidak ditemukan'], 404);
            }

            if (!in_array($pengajuanIzin->status_pengajuan, ['diajukan', 'disetujui'])) {
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" atau "disetujui" yang dapat dibatalkan'], 422);
            }

            $oldData = $pengajuanIzin->getOriginal();
            
            if ($pengajuanIzin->status_pengajuan === 'disetujui') {
                $this->deleteAbsensiForIzin($pengajuanIzin);
            }

            $pengajuanIzin->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'keterangan' => $request->keterangan,
                'tgl_disetujui' => null,
                'approved_by' => null
            ]);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('reject', $pengajuanIzin, $oldData);
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dibatalkan',
                'data' => $this->formatPengajuanIzin($pengajuanIzin->fresh(['pegawai.unitKerja', 'jenisIzin', 'approver']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan pengajuan: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Batch approve pengajuan
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'keterangan' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanList = SimpegPengajuanIzinDosen::whereIn('id', $request->ids)
                ->whereIn('status_pengajuan', ['diajukan', 'ditolak'])
                ->get();

            if ($pengajuanList->isEmpty()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada pengajuan yang valid untuk disetujui.'], 404);
            }

            foreach ($pengajuanList as $pengajuan) {
                $pengajuan->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'approved_by' => Auth::user()->nama,
                    'keterangan' => $request->keterangan,
                    'tgl_ditolak' => null
                ]);
                $this->createAbsensiForIzin($pengajuan, $request->keterangan);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyetujui " . $pengajuanList->count() . " pengajuan izin",
                'updated_count' => $pengajuanList->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch approve: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Batch reject pengajuan
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'keterangan' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $pengajuanList = SimpegPengajuanIzinDosen::whereIn('id', $request->ids)
                ->whereIn('status_pengajuan', ['diajukan', 'disetujui'])
                ->get();

            if ($pengajuanList->isEmpty()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada pengajuan yang valid untuk dibatalkan.'], 404);
            }
            
            foreach ($pengajuanList as $pengajuan) {
                if ($pengajuan->status_pengajuan === 'disetujui') {
                    $this->deleteAbsensiForIzin($pengajuan);
                }
                $pengajuan->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'keterangan' => $request->keterangan,
                    'tgl_disetujui' => null,
                    'approved_by' => null
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil membatalkan " . $pengajuanList->count() . " pengajuan izin",
                'updated_count' => $pengajuanList->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch reject: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Get statistik untuk dashboard admin
     */
    public function getStatistics(Request $request)
    {
        try {
            $unitKerjaFilter = $request->unit_kerja;
            $periodeIzinFilter = $request->periode_izin;

            $baseQuery = SimpegPengajuanIzinDosen::query();
            $tableName = (new SimpegPengajuanIzinDosen())->getTable();

            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $baseQuery->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            if ($periodeIzinFilter && $periodeIzinFilter !== 'semua') {
                $this->applyPeriodeIzinFilter($baseQuery, $periodeIzinFilter);
            }

            $statistics = [
                'total_pengajuan' => $baseQuery->clone()->count(),
                'diajukan' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count(),
                'disetujui' => $baseQuery->clone()->where('status_pengajuan', 'disetujui')->count(),
                'dibatalkan' => $baseQuery->clone()->where('status_pengajuan', 'ditolak')->count(),
                'pending_approval' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count()
            ];

            $totalPegawaiQuery = SimpegPegawai::whereHas('jabatanAkademik', function($q) {
                $dosenJabatan = ['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar', 'Dosen'];
                $q->whereIn('jabatan_akademik', $dosenJabatan);
            })->whereRaw('LOWER(status_kerja) = ?', ['aktif']);

            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $totalPegawaiQuery->where('unit_kerja_id', $unitKerjaFilter);
            }
            $totalPegawai = $totalPegawaiQuery->count();

            $pegawaiSudahMengajukanQuery = SimpegPengajuanIzinDosen::query();
             if ($periodeIzinFilter && $periodeIzinFilter !== 'semua') {
                $this->applyPeriodeIzinFilter($pegawaiSudahMengajukanQuery, $periodeIzinFilter);
            }
             if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $pegawaiSudahMengajukanQuery->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            $pegawaiSudahMengajukanCount = $pegawaiSudahMengajukanQuery->distinct('pegawai_id')->count('pegawai_id');
            $statistics['belum_mengajukan'] = max(0, $totalPegawai - $pegawaiSudahMengajukanCount);

            $byJenis = $baseQuery->clone()
                ->join('simpeg_jenis_izin', "{$tableName}.jenis_izin_id", '=', 'simpeg_jenis_izin.id')
                ->groupBy('simpeg_jenis_izin.jenis_izin')
                ->selectRaw('simpeg_jenis_izin.jenis_izin, COUNT(*) as total')
                ->get();

            $statistics['by_jenis_izin'] = $byJenis;

            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
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
        $periodeIzinFilter = $request->periode_izin;

        $query = SimpegPegawai::with(['unitKerja', 'jabatanAkademik', 'statusAktif'])->whereRaw('LOWER(status_kerja) = ?', ['aktif']);

        if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
            $query->where('unit_kerja_id', $unitKerjaFilter);
        }

        $query->whereHas('jabatanAkademik', function($q) {
            $dosenJabatan = ['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar', 'Dosen'];
            $q->whereIn('jabatan_akademik', $dosenJabatan);
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

        if ($periodeIzinFilter && $periodeIzinFilter !== 'semua') {
            $sudahMengajukan = $this->getPegawaiSudahMengajukan($periodeIzinFilter);
            $pegawaiIds = collect($pegawaiIds)->diff($sudahMengajukan);
        }

        $pegawaiBelumMengajukan = SimpegPegawai::with(['unitKerja', 'jabatanAkademik'])
            ->whereIn('id', $pegawaiIds)
            ->orderBy('nama', 'asc')
            ->paginate($perPage);
        
        return $this->formatResponseBelumMengajukan($pegawaiBelumMengajukan, $request);
    }

    private function createAbsensiForIzin(SimpegPengajuanIzinDosen $pengajuan, $keteranganAdmin = null)
    {
        $pengajuan->loadMissing('pegawai', 'jenisIzin');

        if(!$pengajuan->pegawai) {
            throw new \Exception("Data pegawai tidak ditemukan untuk pengajuan izin ID: " . $pengajuan->id);
        }
        
        if(!$pengajuan->jenisIzin || !$pengajuan->jenisIzin->jenis_kehadiran_id) {
            throw new \Exception("Jenis Kehadiran tidak terhubung dengan Jenis Izin ID: " . $pengajuan->jenis_izin_id);
        }

        $period = CarbonPeriod::create($pengajuan->tgl_mulai, $pengajuan->tgl_selesai);
        $user = Auth::user();

        foreach ($period as $date) {
            $tanggalAbsensiStr = $date->format('Y-m-d');
            
            // PERBAIKAN: Gunakan keterangan dari admin jika ada, jika tidak, buat otomatis
            $keteranganFinal = $keteranganAdmin;
            if (empty($keteranganFinal)) {
                $keteranganFinal = sprintf(
                    'Izin disetujui secara otomatis untuk: %s. Disetujui oleh: %s.',
                    $pengajuan->jenisIzin->jenis_izin ?? 'N/A',
                    $user->nama ?? 'Sistem'
                );
            }
            
            $attributes = [
                'pegawai_id' => $pengajuan->pegawai_id,
                'tanggal_absensi' => $tanggalAbsensiStr,
                'jenis_kehadiran_id' => $pengajuan->jenisIzin->jenis_kehadiran_id,
                'cuti_record_id' => null,
                'izin_record_id' => $pengajuan->id,
                'check_sum_absensi' => md5($pengajuan->pegawai_id . $tanggalAbsensiStr . 'izin' . $pengajuan->id),
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

    private function deleteAbsensiForIzin(SimpegPengajuanIzinDosen $pengajuan)
    {
        SimpegAbsensiRecord::where('izin_record_id', $pengajuan->id)->delete();
    }
    
    private function getPegawaiSudahMengajukan($periodeIzin)
    {
        $query = SimpegPengajuanIzinDosen::select('pegawai_id');
        $this->applyPeriodeIzinFilter($query, $periodeIzin);
        return $query->distinct()->pluck('pegawai_id');
    }

    private function applyPeriodeIzinFilter($query, $periodeIzin)
    {
        $currentYear = Carbon::now()->year;
        
        switch ($periodeIzin) {
            case '01': $query->whereYear('tgl_mulai', $currentYear); break;
            case '02': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-01-01", "{$currentYear}-06-30"]); break;
            case '03': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-07-01", "{$currentYear}-12-31"]); break;
            case '04': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-01-01", "{$currentYear}-03-31"]); break;
            case '05': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-04-01", "{$currentYear}-06-30"]); break;
            case '06': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-07-01", "{$currentYear}-09-30"]); break;
            case '07': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-10-01", "{$currentYear}-12-31"]); break;
        }
    }

    private function formatPengajuanIzin($pengajuan, $includeActions = false)
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
            'nama_pegawai' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . ($pegawai->nama ?? '-') . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $this->getUnitKerjaNama($pegawai),
            'jenis_izin' => $pengajuan->jenisIzin->jenis_izin ?? '-',
            'keperluan' => $pengajuan->alasan_izin ?? '-',
            'lama_izin' => $pengajuan->jumlah_izin . ' hari',
            'status' => $statusInfo['label'],
            'status_info' => $statusInfo,
            'tgl_input' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d-m-Y') : ($pengajuan->created_at ? $pengajuan->created_at->format('d-m-Y') : '-'),
            'detail_data' => [
                'no_izin' => $pengajuan->no_izin,
                'tgl_mulai' => $pengajuan->tgl_mulai ? Carbon::parse($pengajuan->tgl_mulai)->format('d M Y') : '-',
                'tgl_selesai' => $pengajuan->tgl_selesai ? Carbon::parse($pengajuan->tgl_selesai)->format('d M Y') : '-',
                'keterangan_pemohon' => $pengajuan->alasan_izin,
                'keterangan_admin' => $pengajuan->keterangan,
                'file_pendukung' => $pengajuan->file_pendukung ? [
                    'nama_file' => basename($pengajuan->file_pendukung),
                    'url' => Storage::disk('public')->url('pegawai/izin/' . $pengajuan->file_pendukung)
                ] : null,
                'tgl_diajukan' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d M Y H:i:s') : '-',
                'tgl_disetujui' => $pengajuan->tgl_disetujui ? Carbon::parse($pengajuan->tgl_disetujui)->format('d M Y H:i:s') : '-',
                'tgl_ditolak' => $pengajuan->tgl_ditolak ? Carbon::parse($pengajuan->tgl_ditolak)->format('d M Y H:i:s') : '-',
                'approved_by_id' => null,
                'approved_by_name' => $pengajuan->approved_by ?? '-',
            ]
        ];

        if ($includeActions) {
            $data['actions'] = [];
            if ($status === 'diajukan' || $status === 'ditolak') {
                $data['actions']['approve'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}/approve"), 'method' => 'PATCH', 'label' => 'Setujui', 'icon' => 'check', 'color' => 'success'];
            }
            if ($status === 'diajukan' || $status === 'disetujui') {
                $data['actions']['reject'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}/reject"), 'method' => 'PATCH', 'label' => 'Batalkan', 'icon' => 'x', 'color' => 'danger'];
            }
            $data['actions']['view'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}"), 'method' => 'GET', 'label' => 'Lihat Detail', 'icon' => 'eye', 'color' => 'info'];
        }

        return $data;
    }
    
    private function formatResponsePengajuan(LengthAwarePaginator $paginator, Request $request)
    {
        $batchActions = [];
        if ($request->status === 'diajukan' || $request->status === 'ditolak') {
            $batchActions = [
                'approve' => ['url' => url('/api/admin/validasi-izin/batch-approve'), 'method' => 'POST', 'label' => 'Setujui Terpilih', 'color' => 'success'],
            ];
        }
        if ($request->status === 'diajukan' || $request->status === 'disetujui') {
             $batchActions['reject'] = ['url' => url('/api/admin/validasi-izin/batch-reject'), 'method' => 'POST', 'label' => 'Batalkan Terpilih', 'color' => 'danger'];
        }


        return response()->json([
            'success' => true,
            'data' => $paginator->map(function ($item) {
                return $this->formatPengajuanIzin($item, true); 
            }),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem()
            ],
            'batch_actions' => $batchActions
        ]);
    }

    private function formatResponseBelumMengajukan(LengthAwarePaginator $pegawai, Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $pegawai->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nip' => $item->nip ?? '-',
                    'nama_pegawai' => ($item->gelar_depan ? $item->gelar_depan . ' ' : '') . ($item->nama ?? '-') . ($item->gelar_belakang ? ', ' . $item->gelar_belakang : ''),
                    'unit_kerja' => $this->getUnitKerjaNama($item),
                    'jenis_izin' => '-',
                    'keperluan' => '-',
                    'lama_izin' => '-',
                    'status' => 'Belum Diajukan',
                    'tgl_input' => '-',
                    'actions' => [
                        'remind_url' => url("/api/admin/validasi-izin/remind/{$item->id}"),
                        'create_url' => url("/api/admin/validasi-izin/create-for-pegawai/{$item->id}")
                    ]
                ];
            }),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $pegawai->currentPage(),
                'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(),
                'last_page' => $pegawai->lastPage(),
                'from' => $pegawai->firstItem(),
                'to' => $pegawai->lastItem()
            ],
            'batch_actions' => [] 
        ]);
    }

    private function getStatusInfo($status)
    {
        $statusMap = [
            'draft' => ['label' => 'Draft', 'color' => 'secondary', 'icon' => 'edit'],
            'diajukan' => ['label' => 'Diajukan', 'color' => 'info', 'icon' => 'clock'],
            'disetujui' => ['label' => 'Disetujui', 'color' => 'success', 'icon' => 'check-circle'],
            'ditolak' => ['label' => 'Dibatalkan', 'color' => 'danger', 'icon' => 'x-circle'],
            'ditangguhkan' => ['label' => 'Ditangguhkan', 'color' => 'warning', 'icon' => 'pause-circle']
        ];
        return $statusMap[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary', 'icon' => 'circle'];
    }

    public function getFilterOptions()
    {
        $unitKerjaOptions = SimpegUnitKerja::select('id', 'kode_unit', 'nama_unit')
                                            ->orderBy('nama_unit')
                                            ->get()
                                            ->map(function($unit) {
                                                return [
                                                    'id' => $unit->id,
                                                    'nama' => $unit->nama_unit,
                                                    'kode_unit' => $unit->kode_unit,
                                                ];
                                            })
                                            ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja', 'kode_unit' => '']);

        $periodeIzinOptions = [
            ['id' => 'semua', 'label' => 'Semua Periode', 'value' => 'semua'],
            ['id' => '01', 'label' => 'Izin Tahunan', 'value' => '01'],
            ['id' => '02', 'label' => 'Semester 1', 'value' => '02'],
            ['id' => '03', 'label' => 'Semester 2', 'value' => '03'],
            ['id' => '04', 'label' => 'Kuartal 1', 'value' => '04'],
            ['id' => '05', 'label' => 'Kuartal 2', 'value' => '05'],
            ['id' => '06', 'label' => 'Kuartal 3', 'value' => '06'],
            ['id' => '07', 'label' => 'Kuartal 4', 'value' => '07'],
        ];

        $statusFilterOptions = [
            ['id' => 'semua', 'label' => 'Semua Status', 'value' => 'semua'],
            ['id' => 'belum_diajukan', 'label' => 'Belum Diajukan', 'value' => 'belum_diajukan'],
            ['id' => 'diajukan', 'label' => 'Diajukan', 'value' => 'diajukan'],
            ['id' => 'disetujui', 'label' => 'Disetujui', 'value' => 'disetujui'],
            ['id' => 'dibatalkan', 'label' => 'Dibatalkan', 'value' => 'dibatalkan'],
        ];

        $jenisIzinOptions = SimpegJenisIzin::select('id', 'jenis_izin')
                                            ->orderBy('jenis_izin')
                                            ->get()
                                            ->map(function($jenis) {
                                                return ['id' => $jenis->id, 'label' => $jenis->jenis_izin, 'value' => $jenis->id];
                                            })
                                            ->prepend(['id' => 'semua', 'label' => 'Semua Jenis Izin', 'value' => 'semua']);

        return [
            'unit_kerja' => $unitKerjaOptions,
            'periode_izin' => $periodeIzinOptions,
            'status' => $statusFilterOptions,
            'jenis_izin' => $jenisIzinOptions,
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
