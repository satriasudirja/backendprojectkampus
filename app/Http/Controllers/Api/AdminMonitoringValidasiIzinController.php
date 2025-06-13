<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPengajuanIzinDosen;
use App\Models\SimpegJenisIzin;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminMonitoringValidasiIzinController extends Controller
{
    /**
     * Monitoring validasi pengajuan izin untuk admin
     */
    public function index(Request $request)
    {
        try {
            // Pastikan user adalah admin
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Silakan login terlebih dahulu'
                ], 401);
            }

            $perPage = $request->per_page ?? 10;
            $search = $request->search;
            
            // Filter parameters
            $unitKerjaFilter = $request->unit_kerja;
            $periodeIzinFilter = $request->periode_izin; // 01 - Izin Tahunan format
            $statusFilter = $request->status; // belum_diajukan, diajukan, dibatalkan, disetujui
            $jenisIzinFilter = $request->jenis_izin;

            // Base query berdasarkan status
            if ($statusFilter === 'belum_diajukan') {
                // Menampilkan pegawai yang belum mengajukan izin
                return $this->getPegawaiBelumMengajukan($request);
            } else {
                // Menampilkan pengajuan izin yang sudah ada
                $query = SimpegPengajuanIzinDosen::with([
                    'pegawai.unitKerja',
                    'pegawai.jabatanAkademik',
                    'jenisIzin'
                ]);

                // Filter berdasarkan unit kerja
                if ($unitKerjaFilter) {
                    $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                        $q->where('unit_kerja_id', $unitKerjaFilter);
                    });
                }

                // Filter berdasarkan jenis izin
                if ($jenisIzinFilter) {
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
                        $q->where('no_izin', 'like', '%'.$search.'%')
                          ->orWhere('alasan_izin', 'like', '%'.$search.'%')
                          ->orWhereHas('pegawai', function($subQ) use ($search) {
                              $subQ->where('nip', 'like', '%'.$search.'%')
                                   ->orWhere('nama', 'like', '%'.$search.'%');
                          })
                          ->orWhereHas('jenisIzin', function($subQ) use ($search) {
                              $subQ->where('jenis_izin', 'like', '%'.$search.'%');
                          });
                    });
                }

                // Order by tanggal diajukan terbaru
                $query->orderBy('tgl_diajukan', 'desc')
                      ->orderBy('created_at', 'desc');

                $pengajuanIzin = $query->paginate($perPage);

                return $this->formatResponsePengajuan($pengajuanIzin, $request);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'error_code' => 'SYSTEM_ERROR'
            ], 500);
        }
    }

    /**
     * Get pegawai yang belum mengajukan izin
     */
    private function getPegawaiBelumMengajukan($request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaFilter = $request->unit_kerja;
        $periodeIzinFilter = $request->periode_izin;

        // Query pegawai aktif
        $query = SimpegPegawai::with([
            'unitKerja',
            'jabatanAkademik', 
            'statusAktif'
        ])->where(function($q) {
            $q->where('status_kerja', 'Aktif')
              ->orWhere('status_kerja', 'LIKE', '%aktif%');
        });

        // Filter berdasarkan unit kerja
        if ($unitKerjaFilter) {
            $query->where('unit_kerja_id', $unitKerjaFilter);
        }

        // Filter hanya dosen
        $query->whereHas('jabatanAkademik', function($q) {
            $dosenJabatan = ['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar', 'Dosen'];
            $q->whereIn('jabatan_akademik', $dosenJabatan);
        });

        // Search functionality
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nip', 'like', '%'.$search.'%')
                  ->orWhere('nama', 'like', '%'.$search.'%')
                  ->orWhereHas('unitKerja', function($subQ) use ($search) {
                      $subQ->where('nama_unit', 'like', '%'.$search.'%');
                  });
            });
        }

        $pegawaiIds = $query->pluck('id');

        // Exclude pegawai yang sudah mengajukan izin pada periode tertentu
        if ($periodeIzinFilter) {
            $sudahMengajukan = $this->getPegawaiSudahMengajukan($periodeIzinFilter);
            $pegawaiIds = $pegawaiIds->diff($sudahMengajukan);
        }

        // Get final data dengan pagination
        $pegawaiBelumMengajukan = SimpegPegawai::with([
            'unitKerja',
            'jabatanAkademik'
        ])->whereIn('id', $pegawaiIds)
          ->orderBy('nama', 'asc')
          ->paginate($perPage);

        return $this->formatResponseBelumMengajukan($pegawaiBelumMengajukan, $request);
    }

    /**
     * Get pegawai yang sudah mengajukan pada periode tertentu
     */
    private function getPegawaiSudahMengajukan($periodeIzin)
    {
        $query = SimpegPengajuanIzinDosen::select('pegawai_id');
        $this->applyPeriodeIzinFilter($query, $periodeIzin);
        return $query->distinct()->pluck('pegawai_id');
    }

    /**
     * Apply filter periode izin
     */
    private function applyPeriodeIzinFilter($query, $periodeIzin)
    {
        $currentYear = Carbon::now()->year;
        
        switch ($periodeIzin) {
            case '01': // Izin Tahunan
                $query->whereYear('tgl_mulai', $currentYear);
                break;
            case '02': // Semester 1
                $query->whereYear('tgl_mulai', $currentYear)
                      ->whereMonth('tgl_mulai', '>=', 1)
                      ->whereMonth('tgl_mulai', '<=', 6);
                break;
            case '03': // Semester 2  
                $query->whereYear('tgl_mulai', $currentYear)
                      ->whereMonth('tgl_mulai', '>=', 7)
                      ->whereMonth('tgl_mulai', '<=', 12);
                break;
            case '04': // Kuartal 1
                $query->whereYear('tgl_mulai', $currentYear)
                      ->whereMonth('tgl_mulai', '>=', 1)
                      ->whereMonth('tgl_mulai', '<=', 3);
                break;
            case '05': // Kuartal 2
                $query->whereYear('tgl_mulai', $currentYear)
                      ->whereMonth('tgl_mulai', '>=', 4)
                      ->whereMonth('tgl_mulai', '<=', 6);
                break;
            case '06': // Kuartal 3
                $query->whereYear('tgl_mulai', $currentYear)
                      ->whereMonth('tgl_mulai', '>=', 7)
                      ->whereMonth('tgl_mulai', '<=', 9);
                break;
            case '07': // Kuartal 4
                $query->whereYear('tgl_mulai', $currentYear)
                      ->whereMonth('tgl_mulai', '>=', 10)
                      ->whereMonth('tgl_mulai', '<=', 12);
                break;
        }
    }

    /**
     * Get detail pengajuan izin
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
                'jenisIzin'
            ])->find($id);

            if (!$pengajuanIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPengajuanIzin($pengajuanIzin),
                'pegawai' => $this->formatPegawaiInfo($pengajuanIzin->pegawai),
                'timeline' => $this->getTimelinePengajuan($pengajuanIzin)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Setujui pengajuan izin
     */
    public function approvePengajuan(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'keterangan_admin' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $pengajuanIzin = SimpegPengajuanIzinDosen::find($id);

            if (!$pengajuanIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            if ($pengajuanIzin->status_pengajuan !== 'diajukan') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya pengajuan dengan status "diajukan" yang dapat disetujui'
                ], 422);
            }

            $oldData = $pengajuanIzin->getOriginal();

            $pengajuanIzin->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'disetujui_oleh' => Auth::id(),
                'keterangan_admin' => $request->keterangan_admin
            ]);

            // Log activity
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('approve', $pengajuanIzin, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil disetujui',
                'data' => $this->formatPengajuanIzin($pengajuanIzin->fresh(['pegawai', 'jenisIzin']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui pengajuan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tolak/Batalkan pengajuan izin
     */
    public function rejectPengajuan(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'keterangan_admin' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $pengajuanIzin = SimpegPengajuanIzinDosen::find($id);

            if (!$pengajuanIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            if (!in_array($pengajuanIzin->status_pengajuan, ['diajukan', 'disetujui'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya pengajuan dengan status "diajukan" atau "disetujui" yang dapat dibatalkan'
                ], 422);
            }

            $oldData = $pengajuanIzin->getOriginal();

            $pengajuanIzin->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'ditolak_oleh' => Auth::id(),
                'keterangan_admin' => $request->keterangan_admin
            ]);

            // Log activity
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('reject', $pengajuanIzin, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dibatalkan',
                'data' => $this->formatPengajuanIzin($pengajuanIzin->fresh(['pegawai', 'jenisIzin']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan pengajuan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch approve pengajuan
     */
    public function batchApprove(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer',
                'keterangan_admin' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updatedCount = SimpegPengajuanIzinDosen::whereIn('id', $request->ids)
                ->where('status_pengajuan', 'diajukan')
                ->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'disetujui_oleh' => Auth::id(),
                    'keterangan_admin' => $request->keterangan_admin
                ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyetujui {$updatedCount} pengajuan izin",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch approve: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch reject pengajuan
     */
    public function batchReject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer',
                'keterangan_admin' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updatedCount = SimpegPengajuanIzinDosen::whereIn('id', $request->ids)
                ->whereIn('status_pengajuan', ['diajukan', 'disetujui'])
                ->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'ditolak_oleh' => Auth::id(),
                    'keterangan_admin' => $request->keterangan_admin
                ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil membatalkan {$updatedCount} pengajuan izin",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch reject: ' . $e->getMessage()
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

            // Apply filters
            if ($unitKerjaFilter) {
                $baseQuery->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    $q->where('unit_kerja_id', $unitKerjaFilter);
                });
            }

            if ($periodeIzinFilter) {
                $this->applyPeriodeIzinFilter($baseQuery, $periodeIzinFilter);
            }

            // Get statistics
            $statistics = [
                'total_pengajuan' => $baseQuery->count(),
                'diajukan' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count(),
                'disetujui' => $baseQuery->clone()->where('status_pengajuan', 'disetujui')->count(),
                'dibatalkan' => $baseQuery->clone()->where('status_pengajuan', 'ditolak')->count(),
                'pending_approval' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count()
            ];

            // Get belum mengajukan count
            $totalPegawai = SimpegPegawai::whereHas('jabatanAkademik', function($q) {
                $dosenJabatan = ['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar', 'Dosen'];
                $q->whereIn('jabatan_akademik', $dosenJabatan);
            })->when($unitKerjaFilter, function($q) use ($unitKerjaFilter) {
                $q->where('unit_kerja_id', $unitKerjaFilter);
            })->count();

            $sudahMengajukan = $periodeIzinFilter ? 
                $this->getPegawaiSudahMengajukan($periodeIzinFilter)->count() : 
                $baseQuery->distinct('pegawai_id')->count();

            $statistics['belum_mengajukan'] = $totalPegawai - $sudahMengajukan;

            // Statistics by jenis izin
            $byJenis = $baseQuery->clone()
                ->join('simpeg_jenis_izin', 'simpeg_pengajuan_izin_dosen.jenis_izin_id', '=', 'simpeg_jenis_izin.id')
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
                'message' => 'Gagal mengambil statistik: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Format response untuk pengajuan izin
     */
    private function formatResponsePengajuan($pengajuanIzin, $request)
    {
        return response()->json([
            'success' => true,
            'data' => $pengajuanIzin->map(function ($item) {
                return $this->formatPengajuanIzin($item, true);
            }),
            'filter_options' => $this->getFilterOptions(),
            'pagination' => [
                'current_page' => $pengajuanIzin->currentPage(),
                'per_page' => $pengajuanIzin->perPage(),
                'total' => $pengajuanIzin->total(),
                'last_page' => $pengajuanIzin->lastPage(),
                'from' => $pengajuanIzin->firstItem(),
                'to' => $pengajuanIzin->lastItem()
            ],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/validasi-izin/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success'
                ],
                'reject' => [
                    'url' => url("/api/admin/validasi-izin/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Batalkan Terpilih',
                    'icon' => 'x',
                    'color' => 'danger'
                ]
            ]
        ]);
    }

    /**
     * Format response untuk pegawai belum mengajukan
     */
    private function formatResponseBelumMengajukan($pegawai, $request)
    {
        return response()->json([
            'success' => true,
            'data' => $pegawai->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nip' => $item->nip ?? '-',
                    'nama_pegawai' => $item->nama ?? '-',
                    'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
                    'jenis_izin' => '-',
                    'keperluan' => '-',
                    'lama_izin' => '-',
                    'status' => 'Belum Diajukan',
                    'tgl_input' => '-',
                    'aksi' => [
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
            ]
        ]);
    }

    /**
     * Format pengajuan izin data
     */
    private function formatPengajuanIzin($pengajuan, $includeActions = false)
    {
        $status = $pengajuan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        $data = [
            'id' => $pengajuan->id,
            'nip' => $pengajuan->pegawai->nip ?? '-',
            'nama_pegawai' => $pengajuan->pegawai->nama ?? '-',
            'unit_kerja' => $pengajuan->pegawai->unitKerja->nama_unit ?? '-',
            'jenis_izin' => $pengajuan->jenisIzin->jenis_izin ?? '-',
            'keperluan' => $pengajuan->alasan_izin ?? '-',
            'lama_izin' => $pengajuan->jumlah_izin . ' hari',
            'status' => $statusInfo['label'],
            'status_info' => $statusInfo,
            'tgl_input' => $pengajuan->created_at ? $pengajuan->created_at->format('d-m-Y') : '-',
            'detail' => [
                'no_izin' => $pengajuan->no_izin,
                'tgl_mulai' => $pengajuan->tgl_mulai,
                'tgl_selesai' => $pengajuan->tgl_selesai,
                'keterangan' => $pengajuan->keterangan,
                'keterangan_admin' => $pengajuan->keterangan_admin,
                'file_pendukung' => $pengajuan->file_pendukung ? [
                    'nama_file' => $pengajuan->file_pendukung,
                    'url' => url('storage/pegawai/izin/'.$pengajuan->file_pendukung)
                ] : null
            ]
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/validasi-izin/{$pengajuan->id}"),
                'approve_url' => url("/api/admin/validasi-izin/{$pengajuan->id}/approve"),
                'reject_url' => url("/api/admin/validasi-izin/{$pengajuan->id}/reject"),
            ];

            $data['actions'] = [];

            if ($status === 'diajukan') {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success'
                ];

                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Batalkan',
                    'icon' => 'x',
                    'color' => 'danger'
                ];
            }

            if ($status === 'disetujui') {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Batalkan',
                    'icon' => 'x',
                    'color' => 'danger'
                ];
            }

            $data['actions']['view'] = [
                'url' => $data['aksi']['detail_url'],
                'method' => 'GET',
                'label' => 'Lihat Detail',
                'icon' => 'eye',
                'color' => 'info'
            ];
        }

        return $data;
    }

    /**
     * Format pegawai info
     */
    private function formatPegawaiInfo($pegawai)
    {
        $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        
        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => $unitKerjaNama,
            'jab_akademik' => $jabatanAkademikNama,
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-'
        ];
    }

    /**
     * Get status info
     */
    private function getStatusInfo($status)
    {
        $statusMap = [
            'draft' => [
                'label' => 'Draft',
                'color' => 'secondary',
                'icon' => 'edit'
            ],
            'diajukan' => [
                'label' => 'Diajukan',
                'color' => 'info',
                'icon' => 'clock'
            ],
            'disetujui' => [
                'label' => 'Disetujui',
                'color' => 'success',
                'icon' => 'check-circle'
            ],
            'ditolak' => [
                'label' => 'Dibatalkan',
                'color' => 'danger',
                'icon' => 'x-circle'
            ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle'
        ];
    }

    /**
     * Get timeline pengajuan
     */
    private function getTimelinePengajuan($pengajuan)
    {
        $timeline = [];
        $status = $pengajuan->status_pengajuan ?? 'draft';

        $timeline[] = [
            'status' => 'draft',
            'label' => 'Draft',
            'tanggal' => $pengajuan->created_at ? $pengajuan->created_at->format('d-m-Y H:i') : '-',
            'is_completed' => true
        ];

        if (in_array($status, ['diajukan', 'disetujui', 'ditolak'])) {
            $timeline[] = [
                'status' => 'diajukan',
                'label' => 'Diajukan',
                'tanggal' => $pengajuan->tgl_diajukan ? Carbon::parse($pengajuan->tgl_diajukan)->format('d-m-Y H:i') : '-',
                'is_completed' => true
            ];
        }

        if ($status === 'disetujui') {
            $timeline[] = [
                'status' => 'disetujui',
                'label' => 'Disetujui',
                'tanggal' => $pengajuan->tgl_disetujui ? Carbon::parse($pengajuan->tgl_disetujui)->format('d-m-Y H:i') : '-',
                'is_completed' => true
            ];
        }

        if ($status === 'ditolak') {
            $timeline[] = [
                'status' => 'ditolak',
                'label' => 'Dibatalkan',
                'tanggal' => $pengajuan->tgl_ditolak ? Carbon::parse($pengajuan->tgl_ditolak)->format('d-m-Y H:i') : '-',
                'is_completed' => true
            ];
        }

        return $timeline;
    }

    /**
     * Get filter options
     */
    private function getFilterOptions()
    {
        return [
            'unit_kerja' => SimpegUnitKerja::select('id', 'kode_unit', 'nama_unit')
                                          ->orderBy('nama_unit')
                                          ->get()
                                          ->map(function($unit) {
                                              return [
                                                  'id' => $unit->id,
                                                  'value' => $unit->id,
                                                  'label' => $unit->nama_unit
                                              ];
                                          }),
            'periode_izin' => [
                ['value' => '', 'label' => 'Semua Periode'],
                ['value' => '01', 'label' => 'Izin Tahunan'],
                ['value' => '02', 'label' => 'Semester 1'],
                ['value' => '03', 'label' => 'Semester 2'],
                ['value' => '04', 'label' => 'Kuartal 1'],
                ['value' => '05', 'label' => 'Kuartal 2'],
                ['value' => '06', 'label' => 'Kuartal 3'],
                ['value' => '07', 'label' => 'Kuartal 4']
            ],
            'status' => [
                ['value' => 'semua', 'label' => 'Semua Status'],
                ['value' => 'belum_diajukan', 'label' => 'Belum Diajukan'],
                ['value' => 'diajukan', 'label' => 'Diajukan'],
                ['value' => 'disetujui', 'label' => 'Disetujui'],
                ['value' => 'dibatalkan', 'label' => 'Dibatalkan']
            ],
            'jenis_izin' => SimpegJenisIzin::select('id', 'jenis_izin')
                                          ->orderBy('jenis_izin')
                                          ->get()
                                          ->map(function($jenis) {
                                              return [
                                                  'id' => $jenis->id,
                                                  'value' => $jenis->id,
                                                  'label' => $jenis->jenis_izin
                                              ];
                                          })
        ];
    }
}