<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegCutiRecord;
use App\Models\SimpegDaftarCuti;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminMonitoringValidasiCutiController extends Controller
{
    /**
     * Monitoring validasi pengajuan cuti untuk admin.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

            $query = SimpegCutiRecord::with(['pegawai.unitKerja', 'pegawai.jabatanAkademik', 'jenisCuti', 'approver']);

            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            if ($jenisCutiFilter && $jenisCutiFilter !== 'semua') {
                $query->where('jenis_cuti_id', $jenisCutiFilter);
            }

            if ($periodeCutiFilter) {
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

            $query->orderBy('tgl_diajukan', 'desc')->orderBy('created_at', 'desc');
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
     * Get pegawai yang belum mengajukan cuti.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function getPegawaiBelumMengajukan(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaFilter = $request->unit_kerja;
        $periodeCutiFilter = $request->periode_cuti;

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

        if ($periodeCutiFilter && $periodeCutiFilter !== 'semua') {
            $sudahMengajukan = $this->getPegawaiSudahMengajukan($periodeCutiFilter);
            $pegawaiIds = collect($pegawaiIds)->diff($sudahMengajukan);
        }

        $pegawaiBelumMengajukan = SimpegPegawai::with(['unitKerja', 'jabatanAkademik'])
            ->whereIn('id', $pegawaiIds)
            ->orderBy('nama', 'asc')
            ->paginate($perPage);

        return $this->formatResponseBelumMengajukan($pegawaiBelumMengajukan);
    }

    /**
     * Get detail pengajuan cuti.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // FIX: Validate that the ID is numeric to prevent routing conflicts with "batch"
        if (!is_numeric($id)) {
            return response()->json(['success' => false, 'message' => 'ID pengajuan tidak valid.'], 400);
        }

        try {
            $pengajuanCuti = SimpegCutiRecord::with([
                'pegawai' => function($query) {
                    $query->with([
                        'unitKerja', 'statusAktif', 'jabatanAkademik',
                        'dataJabatanFungsional.jabatanFungsional',
                        'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                        'dataPendidikanFormal.jenjangPendidikan'
                    ]);
                }, 'jenisCuti', 'approver'
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
     * Setujui pengajuan cuti.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approvePengajuan(Request $request, $id)
    {
        // FIX: Validate that the ID is numeric to prevent routing conflicts with "batch"
        if (!is_numeric($id)) {
            return response()->json(['success' => false, 'message' => 'ID pengajuan tidak valid.'], 400);
        }

        try {
            $validator = Validator::make($request->all(), ['keterangan_admin' => 'nullable|string|max:500']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $pengajuanCuti = SimpegCutiRecord::find($id);
            if (!$pengajuanCuti) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan cuti tidak ditemukan'], 404);
            }

            if ($pengajuanCuti->status_pengajuan !== 'diajukan') {
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" yang dapat disetujui'], 422);
            }

            $oldData = $pengajuanCuti->getOriginal();
            $pengajuanCuti->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'disetujui_oleh' => Auth::id(),
                'keterangan_admin' => $request->keterangan_admin
            ]);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('approve', $pengajuanCuti, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil disetujui',
                'data' => $this->formatPengajuanCuti($pengajuanCuti->fresh(['pegawai.unitKerja', 'jenisCuti', 'approver']), true)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui pengajuan: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Tolak/Batalkan pengajuan cuti.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectPengajuan(Request $request, $id)
    {
        // FIX: Validate that the ID is numeric to prevent routing conflicts with "batch"
        if (!is_numeric($id)) {
            return response()->json(['success' => false, 'message' => 'ID pengajuan tidak valid.'], 400);
        }

        try {
            $validator = Validator::make($request->all(), ['keterangan_admin' => 'required|string|max:500']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $pengajuanCuti = SimpegCutiRecord::find($id);
            if (!$pengajuanCuti) {
                return response()->json(['success' => false, 'message' => 'Data pengajuan cuti tidak ditemukan'], 404);
            }

            if (!in_array($pengajuanCuti->status_pengajuan, ['diajukan', 'disetujui'])) {
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" atau "disetujui" yang dapat dibatalkan'], 422);
            }

            $oldData = $pengajuanCuti->getOriginal();
            $pengajuanCuti->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'ditolak_oleh' => Auth::id(),
                'keterangan_admin' => $request->keterangan_admin
            ]);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('reject', $pengajuanCuti, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil dibatalkan',
                'data' => $this->formatPengajuanCuti($pengajuanCuti->fresh(['pegawai.unitKerja', 'jenisCuti', 'approver']), true)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membatalkan pengajuan: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Batch approve pengajuan.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'keterangan_admin' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanList = SimpegCutiRecord::whereIn('id', $request->ids)->where('status_pengajuan', 'diajukan')->get();
            $updatedCount = 0;
            foreach ($pengajuanList as $pengajuan) {
                $pengajuan->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'disetujui_oleh' => Auth::id(),
                    'keterangan_admin' => $request->keterangan_admin
                ]);
                $updatedCount++;
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => "Berhasil menyetujui {$updatedCount} pengajuan cuti", 'updated_count' => $updatedCount]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal batch approve: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Batch reject pengajuan.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'keterangan_admin' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $pengajuanList = SimpegCutiRecord::whereIn('id', $request->ids)->whereIn('status_pengajuan', ['diajukan', 'disetujui'])->get();
            $updatedCount = 0;
            foreach ($pengajuanList as $pengajuan) {
                $pengajuan->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'ditolak_oleh' => Auth::id(),
                    'keterangan_admin' => $request->keterangan_admin
                ]);
                $updatedCount++;
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => "Berhasil membatalkan {$updatedCount} pengajuan cuti", 'updated_count' => $updatedCount]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal batch reject: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Get statistik untuk dashboard admin.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        try {
            $unitKerjaFilter = $request->unit_kerja;
            $periodeCutiFilter = $request->periode_cuti;
            $tableName = (new SimpegCutiRecord())->getTable();

            $baseQuery = SimpegCutiRecord::query();
            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $baseQuery->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }
            if ($periodeCutiFilter) {
                $this->applyPeriodeCutiFilter($baseQuery, $periodeCutiFilter);
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
            });
            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $totalPegawaiQuery->where('unit_kerja_id', $unitKerjaFilter);
            }
            $totalPegawai = $totalPegawaiQuery->count();
            $pegawaiSudahMengajukan = $baseQuery->clone()->distinct('pegawai_id')->pluck('pegawai_id');
            $statistics['belum_mengajukan'] = $totalPegawai - $pegawaiSudahMengajukan->count();

            $byJenis = $baseQuery->clone()
                ->join('simpeg_daftar_cuti', "{$tableName}.jenis_cuti_id", '=', 'simpeg_daftar_cuti.id')
                ->groupBy('simpeg_daftar_cuti.nama_jenis_cuti')
                ->selectRaw('simpeg_daftar_cuti.nama_jenis_cuti, COUNT(*) as total')
                ->get();
            $statistics['by_jenis_cuti'] = $byJenis;

            return response()->json(['success' => true, 'statistics' => $statistics]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil statistik: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }
    
    // =========================================================
    // HELPER METHODS
    // =========================================================

    /**
     * Get pegawai yang sudah mengajukan pada periode tertentu.
     * @param string $periodeCuti
     * @return \Illuminate\Support\Collection
     */
    private function getPegawaiSudahMengajukan($periodeCuti)
    {
        $query = SimpegCutiRecord::select('pegawai_id');
        $this->applyPeriodeCutiFilter($query, $periodeCuti);
        return $query->distinct()->pluck('pegawai_id');
    }

    /**
     * Apply filter periode cuti.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $periodeCuti
     */
    private function applyPeriodeCutiFilter($query, $periodeCuti)
    {
        $currentYear = Carbon::now()->year;
        switch ($periodeCuti) {
            case '01': $query->whereYear('tgl_mulai', $currentYear); break; // Tahunan
            case '02': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-01-01", "{$currentYear}-06-30"]); break; // Semester 1
            case '03': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-07-01", "{$currentYear}-12-31"]); break; // Semester 2
            case '04': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-01-01", "{$currentYear}-03-31"]); break; // Kuartal 1
            case '05': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-04-01", "{$currentYear}-06-30"]); break; // Kuartal 2
            case '06': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-07-01", "{$currentYear}-09-30"]); break; // Kuartal 3
            case '07': $query->whereYear('tgl_mulai', $currentYear)->whereBetween('tgl_mulai', ["{$currentYear}-10-01", "{$currentYear}-12-31"]); break; // Kuartal 4
        }
    }

    /**
     * Format response untuk pengajuan cuti yang sudah ada.
     * @param LengthAwarePaginator $paginator
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatResponsePengajuan(LengthAwarePaginator $paginator, Request $request)
    {
        $batchActions = [];
        if ($request->status === 'diajukan') {
            $batchActions = [
                'approve' => ['url' => url('/api/admin/validasi-cuti/batch-approve'), 'method' => 'POST', 'label' => 'Setujui Terpilih', 'color' => 'success'],
                'reject' => ['url' => url('/api/admin/validasi-cuti/batch-reject'), 'method' => 'POST', 'label' => 'Batalkan Terpilih', 'color' => 'danger']
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $paginator->map(fn($item) => $this->formatPengajuanCuti($item, true)),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(),
                'total' => $paginator->total(), 'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()
            ],
            'batch_actions' => $batchActions
        ]);
    }

    /**
     * Format response untuk pegawai yang belum mengajukan.
     * @param LengthAwarePaginator $pegawai
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatResponseBelumMengajukan(LengthAwarePaginator $pegawai)
    {
        return response()->json([
            'success' => true,
            'data' => $pegawai->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nip' => $item->nip ?? '-',
                    'nama_pegawai' => ($item->gelar_depan ? $item->gelar_depan . ' ' : '') . $item->nama . ($item->gelar_belakang ? ', ' . $item->gelar_belakang : ''),
                    'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
                    'jenis_cuti' => '-', 'keperluan' => '-', 'lama_cuti' => '-',
                    'status' => 'Belum Diajukan', 'tgl_input' => '-',
                    'actions' => [
                        'remind_url' => url("/api/admin/validasi-cuti/remind/{$item->id}"),
                        'create_url' => url("/api/admin/validasi-cuti/create-for-pegawai/{$item->id}")
                    ]
                ];
            }),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $pegawai->currentPage(), 'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(), 'last_page' => $pegawai->lastPage(),
                'from' => $pegawai->firstItem(), 'to' => $pegawai->lastItem()
            ]
        ]);
    }

    /**
     * Format data satu pengajuan cuti.
     * @param SimpegCutiRecord $pengajuan
     * @param bool $includeActions
     * @return array|null
     */
    private function formatPengajuanCuti($pengajuan, $includeActions = false)
    {
        if (!$pengajuan) return null;

        $pegawai = $pengajuan->pegawai;
        $status = $pengajuan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        $data = [
            'id' => $pengajuan->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_pegawai' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
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
                'keterangan_admin' => $pengajuan->keterangan_admin,
                'file_cuti' => $pengajuan->file_cuti_url ? ['nama_file' => basename($pengajuan->file_cuti), 'url' => $pengajuan->file_cuti_url] : null,
                'tgl_diajukan' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d M Y H:i:s') : '-',
                'tgl_disetujui' => $pengajuan->tgl_disetujui ? Carbon::parse($pengajuan->tgl_disetujui)->format('d M Y H:i:s') : '-',
                'tgl_ditolak' => $pengajuan->tgl_ditolak ? Carbon::parse($pengajuan->tgl_ditolak)->format('d M Y H:i:s') : '-',
                'approved_by_name' => $pengajuan->approver ? $pengajuan->approver->nama : '-'
            ]
        ];

        if ($includeActions) {
            $data['actions'] = [];
            if ($status === 'diajukan') {
                $data['actions']['approve'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}/approve"), 'method' => 'PATCH', 'label' => 'Setujui', 'icon' => 'check', 'color' => 'success'];
                $data['actions']['reject'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}/reject"), 'method' => 'PATCH', 'label' => 'Batalkan', 'icon' => 'x', 'color' => 'danger'];
            }
            if ($status === 'disetujui') {
                $data['actions']['reject'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}/reject"), 'method' => 'PATCH', 'label' => 'Batalkan', 'icon' => 'x', 'color' => 'danger'];
            }
            $data['actions']['view'] = ['url' => url("/api/admin/validasi-cuti/{$pengajuan->id}"), 'method' => 'GET', 'label' => 'Lihat Detail', 'icon' => 'eye', 'color' => 'info'];
        }
        return $data;
    }

    /**
     * Get status info (label, color, icon).
     * @param string $status
     * @return array
     */
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

    /**
     * Get timeline pengajuan.
     * @param SimpegCutiRecord $pengajuan
     * @return array
     */
    private function getTimelinePengajuan($pengajuan)
    {
        $timeline = [];
        $timeline[] = ['status' => 'draft', 'label' => 'Dibuat', 'tanggal' => $pengajuan->created_at ? $pengajuan->created_at->format('d-m-Y H:i') : '-', 'is_completed' => true];
        if (in_array($pengajuan->status_pengajuan, ['diajukan', 'disetujui', 'ditolak'])) {
            $timeline[] = ['status' => 'diajukan', 'label' => 'Diajukan', 'tanggal' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d-m-Y H:i') : '-', 'is_completed' => true];
        }
        if ($pengajuan->status_pengajuan === 'disetujui') {
            $timeline[] = ['status' => 'disetujui', 'label' => 'Disetujui', 'tanggal' => $pengajuan->tgl_disetujui ? Carbon::parse($pengajuan->tgl_disetujui)->format('d-m-Y H:i') : '-', 'is_completed' => true];
        }
        if ($pengajuan->status_pengajuan === 'ditolak') {
            $timeline[] = ['status' => 'ditolak', 'label' => 'Dibatalkan', 'tanggal' => $pengajuan->tgl_ditolak ? Carbon::parse($pengajuan->tgl_ditolak)->format('d-m-Y H:i') : '-', 'is_completed' => true];
        }
        return $timeline;
    }

    /**
     * Get filter options for frontend.
     * @return array
     */
    private function getFilterOptions()
    {
        return [
            'unit_kerja' => SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit')->get()->map(fn($u) => ['id' => $u->id, 'value' => $u->id, 'label' => $u->nama_unit])->prepend(['id' => 'semua', 'value' => 'semua', 'label' => 'Semua Unit Kerja']),
            'periode_cuti' => [['value' => 'semua', 'label' => 'Semua Periode'], ['value' => '01', 'label' => 'Cuti Tahunan'], ['value' => '02', 'label' => 'Semester 1'], ['value' => '03', 'label' => 'Semester 2'], ['value' => '04', 'label' => 'Kuartal 1'], ['value' => '05', 'label' => 'Kuartal 2'], ['value' => '06', 'label' => 'Kuartal 3'], ['value' => '07', 'label' => 'Kuartal 4']],
            'status' => [['value' => 'semua', 'label' => 'Semua Status'], ['value' => 'belum_diajukan', 'label' => 'Belum Diajukan'], ['value' => 'diajukan', 'label' => 'Diajukan'], ['value' => 'disetujui', 'label' => 'Disetujui'], ['value' => 'dibatalkan', 'label' => 'Dibatalkan']],
            'jenis_cuti' => SimpegDaftarCuti::select('id', 'nama_jenis_cuti')->orderBy('nama_jenis_cuti')->get()->map(fn($j) => ['id' => $j->id, 'value' => $j->id, 'label' => $j->nama_jenis_cuti])->prepend(['id' => 'semua', 'value' => 'semua', 'label' => 'Semua Jenis Cuti']),
        ];
    }
}
