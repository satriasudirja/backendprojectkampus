<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPengajuanIzinDosen;
use App\Models\SimpegJenisIzin;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminMonitoringValidasiIzinController extends Controller
{
    // =========================================================
    // PUBLIC API ENDPOINTS
    // (Methods directly accessible via routes)
    // =========================================================

    /**
     * Monitoring validasi pengajuan izin untuk admin.
     * Mengelola daftar pengajuan izin berdasarkan berbagai filter.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Silakan login terlebih dahulu'
                ], 401);
            }

            // Handle the 'belum_diajukan' status by redirecting to the specific method
            if ($request->status === 'belum_diajukan') {
                return $this->getPegawaiBelumMengajukan($request);
            }

            $perPage = $request->per_page ?? 10;
            $search = $request->search;
            
            $unitKerjaFilter = $request->unit_kerja;
            $periodeIzinFilter = $request->periode_izin;
            $statusFilter = $request->status;
            $jenisIzinFilter = $request->jenis_izin;

            // Base query for existing izin submissions, eager-loading relationships to prevent N+1 issues
            $query = SimpegPengajuanIzinDosen::with([
                'pegawai.unitKerja',
                'pegawai.jabatanAkademik',
                'jenisIzin',
                'approver' // Eager load the approver
            ]);

            // Filter berdasarkan unit kerja
            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            // Filter berdasarkan jenis izin
            if ($jenisIzinFilter && $jenisIzinFilter !== 'semua') {
                $query->where('jenis_izin_id', $jenisIzinFilter);
            }

            // Filter berdasarkan periode izin
            if ($periodeIzinFilter) {
                $this->applyPeriodeIzinFilter($query, $periodeIzinFilter);
            }

            // Filter berdasarkan status pengajuan
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

            // Search functionality
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

            $query->orderBy('tgl_diajukan', 'desc')
                  ->orderBy('created_at', 'desc');

            $pengajuanIzin = $query->paginate($perPage);

            return $this->formatResponsePengajuan($pengajuanIzin, $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'error_code' => 'SYSTEM_ERROR',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Endpoint untuk mendapatkan daftar pegawai yang belum mengajukan izin
     * (untuk filter status 'belum_diajukan').
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPegawaiBelumMengajukan(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaFilter = $request->unit_kerja;
        $periodeIzinFilter = $request->periode_izin;

        $query = SimpegPegawai::with([
            'unitKerja',
            'jabatanAkademik',
            'statusAktif'
        ])->where(function($q) {
            // Robustly check for active status, case-insensitive
            $q->whereRaw('LOWER(status_kerja) = ?', ['aktif']);
        });

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

        $pegawaiBelumMengajukan = SimpegPegawai::with([
            'unitKerja',
            'jabatanAkademik'
        ])->whereIn('id', $pegawaiIds)
          ->orderBy('nama', 'asc')
          ->paginate($perPage);

        return $this->formatResponseBelumMengajukan($pegawaiBelumMengajukan, $request);
    }

    /**
     * Get detail pengajuan izin
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $pengajuanIzin = SimpegPengajuanIzinDosen::with([
                'pegawai' => function($query) {
                    $query->with([
                        'unitKerja',
                        'statusAktif', 
                        'jabatanAkademik',
                        'dataJabatanFungsional.jabatanFungsional',
                        'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                        'dataPendidikanFormal.jenjangPendidikan'
                    ]);
                },
                'jenisIzin',
                'approver' // Eager load approver for detail view
            ])->find($id);

            if (!$pengajuanIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPengajuanIzin($pengajuanIzin, true), // include actions for detail view
                // 'pegawai_detail' => $this->formatPegawaiInfo($pengajuanIzin->pegawai), // Assuming this helper exists
                // 'timeline' => $this->getTimelinePengajuan($pengajuanIzin) // Assuming this helper exists
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
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approvePengajuan(Request $request, $id)
    {
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

            if ($pengajuanIzin->status_pengajuan !== 'diajukan') {
                return response()->json(['success' => false, 'message' => 'Hanya pengajuan dengan status "diajukan" yang dapat disetujui'], 422);
            }

            $oldData = $pengajuanIzin->getOriginal();

            $pengajuanIzin->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'approved_by' => Auth::id(),
                'keterangan' => $request->keterangan
            ]);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('approve', $pengajuanIzin, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil disetujui',
                'data' => $this->formatPengajuanIzin($pengajuanIzin->fresh(['pegawai.unitKerja', 'jenisIzin', 'approver']))
            ]);

        } catch (\Exception $e) {
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
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectPengajuan(Request $request, $id)
    {
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

            $pengajuanIzin->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'keterangan' => $request->keterangan
            ]);

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log('reject', $pengajuanIzin, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dibatalkan',
                'data' => $this->formatPengajuanIzin($pengajuanIzin->fresh(['pegawai.unitKerja', 'jenisIzin', 'approver']))
            ]);

        } catch (\Exception $e) {
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                ->where('status_pengajuan', 'diajukan')
                ->get();

            $updatedCount = 0;
            foreach ($pengajuanList as $pengajuan) {
                $pengajuan->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'approved_by' => Auth::id(),
                    'keterangan' => $request->keterangan
                ]);
                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyetujui {$updatedCount} pengajuan izin",
                'updated_count' => $updatedCount
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

            $updatedCount = 0;
            foreach ($pengajuanList as $pengajuan) {
                $pengajuan->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'keterangan' => $request->keterangan
                ]);
                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil membatalkan {$updatedCount} pengajuan izin",
                'updated_count' => $updatedCount
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        try {
            $unitKerjaFilter = $request->unit_kerja;
            $periodeIzinFilter = $request->periode_izin;

            $baseQuery = SimpegPengajuanIzinDosen::query();
            $tableName = (new SimpegPengajuanIzinDosen())->getTable();


            // Apply filters
            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $baseQuery->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            if ($periodeIzinFilter) {
                $this->applyPeriodeIzinFilter($baseQuery, $periodeIzinFilter);
            }

            // Get statistics
            $statistics = [
                'total_pengajuan' => $baseQuery->clone()->count(),
                'diajukan' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count(),
                'disetujui' => $baseQuery->clone()->where('status_pengajuan', 'disetujui')->count(),
                'dibatalkan' => $baseQuery->clone()->where('status_pengajuan', 'ditolak')->count(),
                'pending_approval' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count()
            ];

            // Get belum mengajukan count
            $totalPegawaiQuery = SimpegPegawai::whereHas('jabatanAkademik', function($q) {
                $dosenJabatan = ['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar', 'Dosen'];
                $q->whereIn('jabatan_akademik', $dosenJabatan);
            });

            if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
                $totalPegawaiQuery->where('unit_kerja_id', $unitKerjaFilter);
            }
            $totalPegawai = $totalPegawaiQuery->count();

            $pegawaiSudahMengajukan = $baseQuery->clone()->distinct('pegawai_id')->pluck('pegawai_id');
            $statistics['belum_mengajukan'] = $totalPegawai - count($pegawaiSudahMengajukan);

            // Statistics by jenis izin
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

    /**
     * Get filter options for the frontend
     * @return array
     */
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

    // =========================================================
    // PRIVATE HELPER METHODS
    // =========================================================

    /**
     * Get pegawai who have already applied for leave in a specific period.
     * @param string $periodeIzin
     * @return \Illuminate\Support\Collection
     */
    private function getPegawaiSudahMengajukan($periodeIzin)
    {
        $query = SimpegPengajuanIzinDosen::select('pegawai_id');
        $this->applyPeriodeIzinFilter($query, $periodeIzin);
        return $query->distinct()->pluck('pegawai_id');
    }

    /**
     * Apply date range filters to the query based on the selected period.
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param string $periodeIzin
     * @return void
     */
    private function applyPeriodeIzinFilter($query, $periodeIzin)
    {
        $currentYear = Carbon::now()->year;
        
        switch ($periodeIzin) {
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
     * Format a single leave application record into a structured array for API response.
     * @param SimpegPengajuanIzinDosen $pengajuan
     * @param bool $includeActions
     * @return array|null
     */
    private function formatPengajuanIzin($pengajuan, $includeActions = false)
    {
        if (!$pengajuan) {
            return null;
        }

        $pegawai = $pengajuan->pegawai;
        $status = $pengajuan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        $data = [
            'id' => $pengajuan->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_pegawai' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . ($pegawai->nama ?? '-') . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
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
                    'url' => $pengajuan->file_pendukung_url
                ] : null,
                'tgl_diajukan' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d M Y H:i:s') : '-',
                'tgl_disetujui' => $pengajuan->tgl_disetujui ? Carbon::parse($pengajuan->tgl_disetujui)->format('d M Y H:i:s') : '-',
                'tgl_ditolak' => $pengajuan->tgl_ditolak ? Carbon::parse($pengajuan->tgl_ditolak)->format('d M Y H:i:s') : '-',
                'approved_by_id' => $pengajuan->approved_by,
                'approved_by_name' => $pengajuan->approver ? (($pengajuan->approver->gelar_depan ? $pengajuan->approver->gelar_depan . ' ' : '') . $pengajuan->approver->nama . ($pengajuan->approver->gelar_belakang ? ', ' . $pengajuan->approver->gelar_belakang : '')) : '-',
            ]
        ];

        if ($includeActions) {
            $data['actions'] = [];
            if ($status === 'diajukan') {
                $data['actions']['approve'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}/approve"), 'method' => 'PATCH', 'label' => 'Setujui', 'icon' => 'check', 'color' => 'success'];
                $data['actions']['reject'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}/reject"), 'method' => 'PATCH', 'label' => 'Batalkan', 'icon' => 'x', 'color' => 'danger'];
            }
            if ($status === 'disetujui') {
                $data['actions']['reject'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}/reject"), 'method' => 'PATCH', 'label' => 'Batalkan', 'icon' => 'x', 'color' => 'danger'];
            }
            $data['actions']['view'] = ['url' => url("/api/admin/validasi-izin/{$pengajuan->id}"), 'method' => 'GET', 'label' => 'Lihat Detail', 'icon' => 'eye', 'color' => 'info'];
        }

        return $data;
    }
    
    /**
     * Format the main paginated response for leave applications.
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatResponsePengajuan(LengthAwarePaginator $paginator, Request $request)
    {
        $batchActions = [];
        if ($request->status === 'diajukan') {
            $batchActions = [
                'approve' => ['url' => url('/api/admin/validasi-izin/batch-approve'), 'method' => 'POST', 'label' => 'Setujui Terpilih', 'color' => 'success'],
                'reject' => ['url' => url('/api/admin/validasi-izin/batch-reject'), 'method' => 'POST', 'label' => 'Batalkan Terpilih', 'color' => 'danger']
            ];
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

    /**
     * Format the response for employees who have not yet applied for leave.
     * @param \Illuminate\Pagination\LengthAwarePaginator $pegawai
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatResponseBelumMengajukan(LengthAwarePaginator $pegawai, Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $pegawai->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nip' => $item->nip ?? '-',
                    'nama_pegawai' => ($item->gelar_depan ? $item->gelar_depan . ' ' : '') . ($item->nama ?? '-') . ($item->gelar_belakang ? ', ' . $item->gelar_belakang : ''),
                    'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
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

    /**
     * Provides a standardized structure for status information (label, color, icon).
     * @param string $status
     * @return array
     */
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
}
