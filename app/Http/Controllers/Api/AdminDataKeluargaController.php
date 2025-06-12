<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanFungsional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDataKeluargaController extends Controller
{
    /**
     * Get all data keluarga dari semua pegawai untuk admin
     */
    public function index(Request $request) 
    {
        try {
            $perPage = $request->per_page ?? 10;
            $search = $request->search;
            
            // Filter parameters
            $unitKerjaFilter = $request->unit_kerja;
            $hubunganFilter = $request->hubungan;
            $jabatanFungsionalFilter = $request->jabatan_fungsional;
            $statusFilter = $request->status;

            // Base query dengan relasi ke pegawai
            $query = SimpegDataKeluargaPegawai::with([
                'pegawai.unitKerja',
                'pegawai.jabatanAkademik',
                'pegawai.statusAktif',
                'pegawai.dataJabatanFungsional' => function($q) {
                    $q->with('jabatanFungsional')->latest('tmt_jabatan')->limit(1);
                }
            ]);

            // Filter hanya data yang memiliki hubungan keluarga
            $query->where(function($q) {
                $q->whereNotNull('nama_pasangan')
                  ->orWhereNotNull('status_orangtua')
                  ->orWhereNotNull('nama'); // untuk data anak jika ada
            });

            // Filter by unit kerja
            if ($unitKerjaFilter) {
                $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    if (is_numeric($unitKerjaFilter)) {
                        $unitKerja = SimpegUnitKerja::find($unitKerjaFilter);
                        if ($unitKerja) {
                            $q->whereRaw("unit_kerja_id::text = ?", [$unitKerja->kode_unit]);
                        }
                    } else {
                        $q->whereRaw("unit_kerja_id::text = ?", [$unitKerjaFilter]);
                    }
                });
            }

            // Filter by hubungan keluarga
            if ($hubunganFilter) {
                switch ($hubunganFilter) {
                    case 'pasangan':
                        $query->whereNotNull('nama_pasangan');
                        break;
                    case 'orang_tua':
                        $query->whereNotNull('status_orangtua');
                        break;
                    case 'anak':
                        $query->whereNotNull('status_anak'); // jika ada field ini
                        break;
                }
            }

            // Filter by jabatan fungsional
            if ($jabatanFungsionalFilter) {
                $query->whereHas('pegawai.dataJabatanFungsional', function($q) use ($jabatanFungsionalFilter) {
                    $q->where('jabatan_fungsional_id', $jabatanFungsionalFilter);
                });
            }

            // Filter by status pengajuan
            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('status_pengajuan', $statusFilter);
            }

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    // Search by NIP pegawai
                    $q->whereHas('pegawai', function($subQ) use ($search) {
                        $subQ->where('nip', 'like', '%'.$search.'%');
                    })
                    // Search by nama pegawai
                    ->orWhereHas('pegawai', function($subQ) use ($search) {
                        $subQ->where('nama', 'like', '%'.$search.'%');
                    })
                    // Search by nama pasangan
                    ->orWhere('nama_pasangan', 'like', '%'.$search.'%')
                    // Search by nama orang tua
                    ->orWhere('nama', 'like', '%'.$search.'%')
                    // Search by unit kerja
                    ->orWhereHas('pegawai.unitKerja', function($subQ) use ($search) {
                        $subQ->where('nama_unit', 'like', '%'.$search.'%');
                    });
                });
            }

            // Order by status_pengajuan dan tanggal diajukan
            $query->orderByRaw("
                CASE status_pengajuan 
                    WHEN 'diajukan' THEN 1 
                    WHEN 'draft' THEN 2 
                    WHEN 'disetujui' THEN 3 
                    WHEN 'ditolak' THEN 4 
                    ELSE 5 
                END
            ")
            ->orderBy('tgl_diajukan', 'desc')
            ->orderBy('created_at', 'desc');

            $dataKeluarga = $query->paginate($perPage);

            // Transform data
            $dataKeluarga->getCollection()->transform(function ($item) {
                return $this->formatDataKeluarga($item);
            });

            // Get summary statistics
            $summaryStats = $this->getSummaryStatistics($request);

            // Get filter options
            $filterOptions = $this->getFilterOptions();

            return response()->json([
                'success' => true,
                'summary' => $summaryStats,
                'filter_options' => $filterOptions,
                'data' => $dataKeluarga,
                'filters_applied' => [
                    'unit_kerja' => $unitKerjaFilter,
                    'hubungan' => $hubunganFilter,
                    'jabatan_fungsional' => $jabatanFungsionalFilter,
                    'status' => $statusFilter,
                    'search' => $search
                ],
                'table_columns' => [
                    ['field' => 'nip', 'label' => 'NIP', 'sortable' => true],
                    ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true],
                    ['field' => 'nama_keluarga', 'label' => 'Nama Pasangan/Keluarga', 'sortable' => true],
                    ['field' => 'hubungan', 'label' => 'Hubungan', 'sortable' => true],
                    ['field' => 'tgl_lahir', 'label' => 'Tgl Lahir', 'sortable' => true],
                    ['field' => 'dokumen', 'label' => 'Dokumen', 'sortable' => false],
                    ['field' => 'tgl_diajukan', 'label' => 'Tgl. Diajukan', 'sortable' => true],
                    ['field' => 'status_pengajuan', 'label' => 'Status', 'sortable' => true],
                    ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
                ],
                'pagination' => [
                    'current_page' => $dataKeluarga->currentPage(),
                    'per_page' => $dataKeluarga->perPage(),
                    'total' => $dataKeluarga->total(),
                    'last_page' => $dataKeluarga->lastPage(),
                    'from' => $dataKeluarga->firstItem(),
                    'to' => $dataKeluarga->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail data keluarga
     */
    public function show($id)
    {
        try {
            $dataKeluarga = SimpegDataKeluargaPegawai::with([
                'pegawai.unitKerja',
                'pegawai.jabatanAkademik',
                'pegawai.statusAktif',
                'pegawai.dataJabatanFungsional.jabatanFungsional',
                'pegawai.dataJabatanStruktural.jabatanStruktural'
            ])->find($id);

            if (!$dataKeluarga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data keluarga tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'keluarga_detail' => $this->formatDetailKeluarga($dataKeluarga),
                    'pegawai_info' => $this->formatPegawaiInfo($dataKeluarga->pegawai),
                    'approval_history' => $this->getApprovalHistory($dataKeluarga)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve data keluarga
     */
    public function approve(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $dataKeluarga = SimpegDataKeluargaPegawai::find($id);

            if (!$dataKeluarga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data keluarga tidak ditemukan'
                ], 404);
            }

            if ($dataKeluarga->status_pengajuan !== 'diajukan') {
                return response()->json([
                    'success' => false,
                    'message' => 'Data keluarga tidak dalam status diajukan'
                ], 422);
            }

            $oldData = $dataKeluarga->getOriginal();
            $admin = Auth::user();

            $dataKeluarga->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
                'disetujui_oleh' => $admin->id,
                'keterangan' => $request->keterangan
            ]);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('approve', $dataKeluarga, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data keluarga berhasil disetujui',
                'data' => $this->formatDataKeluarga($dataKeluarga)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject data keluarga
     */
    public function reject(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'keterangan' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $dataKeluarga = SimpegDataKeluargaPegawai::find($id);

            if (!$dataKeluarga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data keluarga tidak ditemukan'
                ], 404);
            }

            if ($dataKeluarga->status_pengajuan !== 'diajukan') {
                return response()->json([
                    'success' => false,
                    'message' => 'Data keluarga tidak dalam status diajukan'
                ], 422);
            }

            $oldData = $dataKeluarga->getOriginal();
            $admin = Auth::user();

            $dataKeluarga->update([
                'status_pengajuan' => 'ditolak',
                'tgl_ditolak' => now(),
                'ditolak_oleh' => $admin->id,
                'keterangan' => $request->keterangan
            ]);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('reject', $dataKeluarga, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data keluarga berhasil ditolak',
                'data' => $this->formatDataKeluarga($dataKeluarga)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch approve
     */
    public function batchApprove(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:simpeg_data_keluarga_pegawai,id',
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = Auth::user();
            $approvedCount = 0;
            $errors = [];

            DB::beginTransaction();
            try {
                foreach ($request->ids as $id) {
                    $dataKeluarga = SimpegDataKeluargaPegawai::find($id);
                    
                    if (!$dataKeluarga) {
                        $errors[] = "Data dengan ID {$id} tidak ditemukan";
                        continue;
                    }

                    if ($dataKeluarga->status_pengajuan !== 'diajukan') {
                        $nama = $dataKeluarga->nama_pasangan ?? $dataKeluarga->nama ?? 'Unknown';
                        $errors[] = "Data {$nama} tidak dalam status diajukan";
                        continue;
                    }

                    $dataKeluarga->update([
                        'status_pengajuan' => 'disetujui',
                        'tgl_disetujui' => now(),
                        'disetujui_oleh' => $admin->id,
                        'keterangan' => $request->keterangan
                    ]);

                    $approvedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menyetujui {$approvedCount} data keluarga",
                    'approved_count' => $approvedCount,
                    'errors' => $errors
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat batch approve: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch reject
     */
    public function batchReject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:simpeg_data_keluarga_pegawai,id',
                'keterangan' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = Auth::user();
            $rejectedCount = 0;
            $errors = [];

            DB::beginTransaction();
            try {
                foreach ($request->ids as $id) {
                    $dataKeluarga = SimpegDataKeluargaPegawai::find($id);
                    
                    if (!$dataKeluarga) {
                        $errors[] = "Data dengan ID {$id} tidak ditemukan";
                        continue;
                    }

                    if ($dataKeluarga->status_pengajuan !== 'diajukan') {
                        $nama = $dataKeluarga->nama_pasangan ?? $dataKeluarga->nama ?? 'Unknown';
                        $errors[] = "Data {$nama} tidak dalam status diajukan";
                        continue;
                    }

                    $dataKeluarga->update([
                        'status_pengajuan' => 'ditolak',
                        'tgl_ditolak' => now(),
                        'ditolak_oleh' => $admin->id,
                        'keterangan' => $request->keterangan
                    ]);

                    $rejectedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menolak {$rejectedCount} data keluarga",
                    'rejected_count' => $rejectedCount,
                    'errors' => $errors
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat batch reject: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get summary statistics
     */
    private function getSummaryStatistics($request)
    {
        $baseQuery = SimpegDataKeluargaPegawai::where(function($q) {
            $q->whereNotNull('nama_pasangan')
              ->orWhereNotNull('status_orangtua')
              ->orWhereNotNull('nama');
        });

        // Apply same filters as main query
        if ($request->unit_kerja) {
            $baseQuery->whereHas('pegawai', function($q) use ($request) {
                if (is_numeric($request->unit_kerja)) {
                    $unitKerja = SimpegUnitKerja::find($request->unit_kerja);
                    if ($unitKerja) {
                        $q->whereRaw("unit_kerja_id::text = ?", [$unitKerja->kode_unit]);
                    }
                } else {
                    $q->whereRaw("unit_kerja_id::text = ?", [$request->unit_kerja]);
                }
            });
        }

        if ($request->hubungan) {
            switch ($request->hubungan) {
                case 'pasangan':
                    $baseQuery->whereNotNull('nama_pasangan');
                    break;
                case 'orang_tua':
                    $baseQuery->whereNotNull('status_orangtua');
                    break;
            }
        }

        return [
            'total' => $baseQuery->count(),
            'draft' => $baseQuery->clone()->where('status_pengajuan', 'draft')->count(),
            'diajukan' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count(),
            'disetujui' => $baseQuery->clone()->where('status_pengajuan', 'disetujui')->count(),
            'ditolak' => $baseQuery->clone()->where('status_pengajuan', 'ditolak')->count(),
            'pending_approval' => $baseQuery->clone()->where('status_pengajuan', 'diajukan')->count()
        ];
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
                                                  'kode_unit' => $unit->kode_unit,
                                                  'nama_unit' => $unit->nama_unit,
                                                  'value' => $unit->id,
                                                  'label' => $unit->nama_unit
                                              ];
                                          }),
            'hubungan' => [
                ['value' => '', 'label' => 'Semua Hubungan'],
                ['value' => 'pasangan', 'label' => 'Pasangan'],
                ['value' => 'orang_tua', 'label' => 'Orang Tua'],
                ['value' => 'anak', 'label' => 'Anak']
            ],
            'jabatan_fungsional' => SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional')
                                                          ->orderBy('nama_jabatan_fungsional')
                                                          ->get()
                                                          ->map(function($jabatan) {
                                                              return [
                                                                  'id' => $jabatan->id,
                                                                  'value' => $jabatan->id,
                                                                  'label' => $jabatan->nama_jabatan_fungsional
                                                              ];
                                                          }),
            'status' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'diajukan', 'label' => 'Diajukan'],
                ['value' => 'disetujui', 'label' => 'Disetujui'],
                ['value' => 'ditolak', 'label' => 'Ditolak']
            ]
        ];
    }

    /**
     * Format data keluarga untuk tabel
     */
    private function formatDataKeluarga($keluarga)
    {
        // Tentukan nama keluarga dan hubungan
        $namaKeluarga = '';
        $hubungan = '';
        $tglLahir = '';
        $dokumen = null;

        if ($keluarga->nama_pasangan) {
            $namaKeluarga = $keluarga->nama_pasangan;
            $hubungan = 'Pasangan';
            $tglLahir = $keluarga->tgl_lahir ? Carbon::parse($keluarga->tgl_lahir)->format('d M Y') : '-';
            
            if ($keluarga->kartu_nikah) {
                $dokumen = [
                    'type' => 'Kartu Nikah',
                    'url' => url('storage/pegawai/keluarga/nikah/' . $keluarga->kartu_nikah),
                    'filename' => $keluarga->kartu_nikah
                ];
            }
        } elseif ($keluarga->status_orangtua) {
            $namaKeluarga = $keluarga->nama ?? '-';
            $hubungan = $keluarga->status_orangtua;
            $tglLahir = $keluarga->tgl_lahir ? Carbon::parse($keluarga->tgl_lahir)->format('d M Y') : '-';
        } else {
            $namaKeluarga = $keluarga->nama ?? '-';
            $hubungan = 'Lainnya';
            $tglLahir = $keluarga->tgl_lahir ? Carbon::parse($keluarga->tgl_lahir)->format('d M Y') : '-';
        }

        // Status info
        $statusInfo = $this->getStatusInfo($keluarga->status_pengajuan ?? 'draft');

        return [
            'id' => $keluarga->id,
            'nip' => $keluarga->pegawai->nip ?? '-',
            'nama_pegawai' => $keluarga->pegawai->nama ?? '-',
            'nama_keluarga' => $namaKeluarga,
            'hubungan' => $hubungan,
            'tgl_lahir' => $tglLahir,
            'dokumen' => $dokumen,
            'tgl_diajukan' => $keluarga->tgl_diajukan ? Carbon::parse($keluarga->tgl_diajukan)->format('d M Y') : '-',
            'status_pengajuan' => $keluarga->status_pengajuan ?? 'draft',
            'status_info' => $statusInfo,
            'unit_kerja' => $keluarga->pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_fungsional' => $this->getJabatanFungsional($keluarga->pegawai),
            'can_approve' => $keluarga->status_pengajuan === 'diajukan',
            'can_reject' => $keluarga->status_pengajuan === 'diajukan',
            'actions' => $this->generateActionLinks($keluarga)
        ];
    }

    /**
     * Format detail keluarga
     */
    private function formatDetailKeluarga($keluarga)
    {
        $detail = [
            'id' => $keluarga->id,
            'basic_info' => [],
            'dokumen' => [],
            'approval_info' => [
                'status_pengajuan' => $keluarga->status_pengajuan ?? 'draft',
                'status_info' => $this->getStatusInfo($keluarga->status_pengajuan ?? 'draft'),
                'tgl_input' => $keluarga->tgl_input,
                'tgl_diajukan' => $keluarga->tgl_diajukan,
                'tgl_disetujui' => $keluarga->tgl_disetujui,
                'tgl_ditolak' => $keluarga->tgl_ditolak,
                'keterangan' => $keluarga->keterangan
            ]
        ];

        // Data spesifik berdasarkan jenis keluarga
        if ($keluarga->nama_pasangan) {
            $detail['basic_info'] = [
                'type' => 'pasangan',
                'nama_pasangan' => $keluarga->nama_pasangan,
                'tempat_lahir' => $keluarga->tempat_lahir,
                'tgl_lahir' => $keluarga->tgl_lahir,
                'jenis_pekerjaan' => $keluarga->jenis_pekerjaan,
                'status_kepegawaian' => $keluarga->status_kepegawaian,
                'karpeg_pasangan' => $keluarga->karpeg_pasangan,
                'tempat_nikah' => $keluarga->tempat_nikah,
                'tgl_nikah' => $keluarga->tgl_nikah,
                'no_akta_nikah' => $keluarga->no_akta_nikah,
                'pasangan_berkerja_dalam_satu_instansi' => $keluarga->pasangan_berkerja_dalam_satu_instansi
            ];

            $detail['dokumen'] = [
                'karpeg_pasangan' => $keluarga->file_karpeg_pasangan ? [
                    'nama_file' => $keluarga->file_karpeg_pasangan,
                    'url' => url('storage/pegawai/keluarga/karpeg/' . $keluarga->file_karpeg_pasangan)
                ] : null,
                'kartu_nikah' => $keluarga->kartu_nikah ? [
                    'nama_file' => $keluarga->kartu_nikah,
                    'url' => url('storage/pegawai/keluarga/nikah/' . $keluarga->kartu_nikah)
                ] : null
            ];
        } elseif ($keluarga->status_orangtua) {
            $detail['basic_info'] = [
                'type' => 'orang_tua',
                'nama' => $keluarga->nama,
                'status_orangtua' => $keluarga->status_orangtua,
                'tempat_lahir' => $keluarga->tempat_lahir,
                'tgl_lahir' => $keluarga->tgl_lahir,
                'umur' => $keluarga->umur,
                'alamat' => $keluarga->alamat,
                'telepon' => $keluarga->telepon,
                'pekerjaan' => $keluarga->pekerjaan
            ];
        }

        return $detail;
    }

    /**
     * Format pegawai info
     */
    private function formatPegawaiInfo($pegawai)
    {
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_akademik' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'jabatan_fungsional' => $this->getJabatanFungsional($pegawai),
            'status_aktif' => $pegawai->statusAktif->nama_status_aktif ?? '-',
            'email' => $pegawai->email_pegawai ?? $pegawai->email_pribadi ?? '-',
            'no_handphone' => $pegawai->no_handphone ?? '-'
        ];
    }

    /**
     * Get jabatan fungsional pegawai
     */
    private function getJabatanFungsional($pegawai)
    {
        if ($pegawai && $pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatan = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            return $jabatan->nama_jabatan_fungsional ?? '-';
        }
        return '-';
    }

    /**
     * Get approval history
     */
    private function getApprovalHistory($keluarga)
    {
        $history = [];

        if ($keluarga->tgl_input) {
            $history[] = [
                'action' => 'Input Data',
                'timestamp' => $keluarga->tgl_input,
                'status' => 'draft',
                'keterangan' => 'Data keluarga diinput'
            ];
        }

        if ($keluarga->tgl_diajukan) {
            $history[] = [
                'action' => 'Pengajuan',
                'timestamp' => $keluarga->tgl_diajukan,
                'status' => 'diajukan',
                'keterangan' => 'Data keluarga diajukan untuk persetujuan'
            ];
        }

        if ($keluarga->tgl_disetujui) {
            $history[] = [
                'action' => 'Disetujui',
                'timestamp' => $keluarga->tgl_disetujui,
                'status' => 'disetujui',
                'keterangan' => $keluarga->keterangan ?? 'Data keluarga disetujui'
            ];
        }

        if ($keluarga->tgl_ditolak) {
            $history[] = [
                'action' => 'Ditolak',
                'timestamp' => $keluarga->tgl_ditolak,
                'status' => 'ditolak',
                'keterangan' => $keluarga->keterangan ?? 'Data keluarga ditolak'
            ];
        }

        return $history;
    }

    /**
     * Generate action links
     */
    private function generateActionLinks($keluarga)
    {
        $baseUrl = request()->getSchemeAndHttpHost();
        $actions = [];

        // Always show detail
        $actions['detail'] = [
            'url' => "{$baseUrl}/api/admin/data-keluarga/{$keluarga->id}",
            'method' => 'GET',
            'label' => 'Detail',
            'icon' => 'eye',
            'color' => 'info'
        ];

        // Show approve/reject only for 'diajukan' status
        if ($keluarga->status_pengajuan === 'diajukan') {
            $actions['approve'] = [
                'url' => "{$baseUrl}/api/admin/data-keluarga/{$keluarga->id}/approve",
                'method' => 'PATCH',
                'label' => 'Setujui',
                'icon' => 'check',
                'color' => 'success',
                'confirm' => true,
                'confirm_message' => 'Apakah Anda yakin ingin menyetujui data keluarga ini?'
            ];

            $actions['reject'] = [
                'url' => "{$baseUrl}/api/admin/data-keluarga/{$keluarga->id}/reject",
                'method' => 'PATCH',
                'label' => 'Tolak',
                'icon' => 'x',
                'color' => 'danger',
                'confirm' => true,
                'confirm_message' => 'Apakah Anda yakin ingin menolak data keluarga ini?',
                'require_reason' => true
            ];
        }

        return $actions;
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
                'icon' => 'edit',
                'description' => 'Belum diajukan'
            ],
            'diajukan' => [
                'label' => 'Diajukan',
                'color' => 'warning',
                'icon' => 'clock',
                'description' => 'Menunggu persetujuan'
            ],
            'disetujui' => [
                'label' => 'Disetujui',
                'color' => 'success',
                'icon' => 'check-circle',
                'description' => 'Telah disetujui'
            ],
            'ditolak' => [
                'label' => 'Ditolak',
                'color' => 'danger',
                'icon' => 'x-circle',
                'description' => 'Ditolak'
            ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }
}