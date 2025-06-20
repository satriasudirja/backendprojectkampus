<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataHubunganKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\HubunganKerja;
use App\Models\SimpegJabatanFungsional;
use App\Models\SimpegJabatanAkademik;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegStatusAktif;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;


class MonitoringHubunganKerjaController extends Controller
{
    // =========================================================
    // PUBLIC API ENDPOINTS
    // (These methods are directly accessible via routes)
    // =========================================================

    /**
     * Monitoring hubungan kerja dengan filter unit kerja, status masa kerja, dan hubungan kerja
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        
        // Filter parameters
        $unitKerjaFilter = $request->unit_kerja; // Sekarang diasumsikan ini adalah 'kode_unit' dari dropdown
        $statusMasaKerjaFilter = $request->status_masa_kerja;
        $hubunganKerjaFilter = $request->hubungan_kerja;
        $levelFilter = $request->level ?? 'seuniv';

        // Base query - ambil hubungan kerja yang aktif
        $query = SimpegDataHubunganKerja::with([
            'pegawai.unitKerja',
            'pegawai.jabatanAkademik',
            'pegawai.dataJabatanFungsional' => function($q) {
                $q->with('jabatanFungsional')->latest('tmt_jabatan')->limit(1);
            },
            'hubunganKerja',
            'statusAktif'
        ])->where('is_aktif', true);

        // Filter berdasarkan unit kerja dan level
        if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
            // FIX: Panggil helper method. Method ini akan mengonversi kode_unit ke ID integer jika diperlukan
            $unitKerjaIds = $this->getUnitKerjaIdsByLevel($unitKerjaFilter, $levelFilter);
            
            $query->whereHas('pegawai', function($q) use ($unitKerjaIds) {
                // FIX: unit_kerja_id di tabel pegawai sekarang diasumsikan merujuk ke ID (integer) dari simpeg_unit_kerja
                $q->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        // Filter berdasarkan jenis hubungan kerja
        if ($hubunganKerjaFilter && $hubunganKerjaFilter !== 'semua') {
            $query->where('hubungan_kerja_id', $hubunganKerjaFilter);
        }

        // Filter berdasarkan status masa kerja
        if ($statusMasaKerjaFilter) {
            // FIX: Panggil helper method
            $query = $this->applyStatusMasaKerjaFilter($query, $statusMasaKerjaFilter);
        }

        // Search functionality
        if ($search) {
            $query->where(function($q) use ($search) {
                $connection = config('database.default');
                $driver = config("database.connections.{$connection}.driver");
                $likeOperator = ($driver === 'pgsql') ? 'ilike' : 'like';

                $q->whereHas('pegawai', function($subQ) use ($search, $likeOperator) {
                    $subQ->where('nip', $likeOperator, '%'.$search.'%');
                })
                ->orWhereHas('pegawai', function($subQ) use ($search, $likeOperator) {
                    $subQ->where('nama', $likeOperator, '%'.$search.'%');
                })
                ->orWhereHas('pegawai.unitKerja', function($subQ) use ($search, $likeOperator) {
                    $subQ->where('nama_unit', $likeOperator, '%'.$search.'%');
                })
                ->orWhereHas('hubunganKerja', function($subQ) use ($search, $likeOperator) {
                    $subQ->where('nama_hub_kerja', $likeOperator, '%'.$search.'%');
                })
                ->orWhere('no_sk', $likeOperator, '%'.$search.'%');
            });
        }

        // Order by nama pegawai
        $query->join('simpeg_pegawai', 'simpeg_data_hubungan_kerja.pegawai_id', '=', 'simpeg_pegawai.id')
              ->orderBy('simpeg_pegawai.nama', 'asc')
              ->select('simpeg_data_hubungan_kerja.*');

        $hubunganKerjaData = $query->paginate($perPage);

        // FIX: Panggil helper method
        $summaryStats = $this->getSummaryStatistics($unitKerjaFilter, $levelFilter, $hubunganKerjaFilter, $statusMasaKerjaFilter);

        // FIX: Panggil metode public getFilterOptions()
        $filterOptions = $this->getFilterOptions();

        return response()->json([
            'success' => true,
            'summary' => $summaryStats,
            'filter_options' => $filterOptions,
            'data' => $hubunganKerjaData->map(function ($item) {
                // FIX: Panggil helper method
                return $this->formatMonitoringData($item);
            }),
            'pagination' => [
                'current_page' => $hubunganKerjaData->currentPage(),
                'per_page' => $hubunganKerjaData->perPage(),
                'total' => $hubunganKerjaData->total(),
                'last_page' => $hubunganKerjaData->lastPage()
            ],
            'filters_applied' => [
                'unit_kerja' => $unitKerjaFilter,
                'level' => $levelFilter,
                'hubungan_kerja' => $hubunganKerjaFilter,
                'status_masa_kerja' => $statusMasaKerjaFilter,
                'search' => $search
            ],
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP', 'sortable' => true],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true],
                ['field' => 'hubungan_kerja', 'label' => 'Hubungan Kerja', 'sortable' => true],
                ['field' => 'jabatan_fungsional', 'label' => 'Fungsional', 'sortable' => false],
                ['field' => 'usia_pensiun', 'label' => 'Usia Pensiun', 'sortable' => false],
                ['field' => 'tanggal_lahir', 'label' => 'Tgl Lahir', 'sortable' => true],
                ['field' => 'tanggal_efektif', 'label' => 'Tgl Efektif', 'sortable' => true],
                ['field' => 'tanggal_berakhir', 'label' => 'Tgl Berakhir', 'sortable' => true],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false],
            ]
        ]);
    }

    /**
     * Download file hubungan kerja
     *
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate->Http->JsonResponse
     */
    public function downloadFile($id)
    {
        $riwayat = SimpegDataHubunganKerja::find($id);

        if (!$riwayat || !$riwayat->file_hubungan_kerja) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $riwayat->file_hubungan_kerja);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di storage'
            ], 404);
        }

        return response()->download($filePath, basename($riwayat->file_hubungan_kerja));
    }

    /**
     * Get detail hubungan kerja by ID dengan riwayat lengkap
     *
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function show($id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::with([
            'pegawai' => function($query) {
                $query->with([
                    'unitKerja',
                    'statusAktif', 
                    'jabatanAkademik',
                    'dataJabatanFungsional' => function($q) {
                        $q->with('jabatanFungsional')->latest('tmt_jabatan')->limit(1);
                    },
                    'dataJabatanStruktural' => function($q) {
                        $q->with('jabatanStruktural.jenisJabatanStruktural')->latest('tgl_mulai')->limit(1);
                    },
                    'dataPendidikanFormal' => function($q) {
                        $q->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
                    },
                    'dataHubunganKerja'
                ]);
            },
            'hubunganKerja',
            'statusAktif'
        ])->find($id);

        if (!$hubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        $riwayatHubunganKerja = SimpegDataHubunganKerja::with(['hubunganKerja', 'statusAktif'])
            ->where('pegawai_id', $hubunganKerja->pegawai_id)
            ->orderBy('tgl_awal', 'desc')
            ->get();

        // FIX: Panggil helper method
        $masaKerja = $this->calculateMasaKerja($hubunganKerja);

        // FIX: Panggil helper method
        $timeline = $this->getStatusTimeline($hubunganKerja);

        return response()->json([
            'success' => true,
            // FIX: Panggil helper method
            'data' => $this->formatRiwayatHubunganKerjaForDetail($hubunganKerja),
            // FIX: Panggil helper method
            'pegawai_detail' => $this->formatPegawaiInfo($hubunganKerja->pegawai),
            'masa_kerja' => $masaKerja,
            'timeline' => $timeline,
            'riwayat_lainnya' => $riwayatHubunganKerja->map(function($item) {
                // FIX: Panggil helper method
                return $this->formatRiwayatHubunganKerjaForDetail($item);
            })->filter(function($item) use ($id) {
                return $item['id'] !== $id;
            })->values(),
            'metadata' => [
                'total_riwayat_pegawai' => $riwayatHubunganKerja->count(),
                'status_aktif_hubungan_kerja' => $hubunganKerja->is_aktif ?? false,
            ]
        ]);
    }

    /**
     * Get semua riwayat hubungan kerja dari pegawai tertentu
     *
     * @param int $pegawaiId
     * @return \Illuminate->Http->JsonResponse
     */
    public function getRiwayatByPegawai($pegawaiId)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional' => function($query) {
                $query->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
            },
            'dataJabatanStruktural' => function($query) {
                $query->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1);
            },
            'dataPendidikanFormal' => function($query) {
                $query->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
            }
        ])->find($pegawaiId);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        $riwayat = SimpegDataHubunganKerja::with(['hubunganKerja', 'statusAktif'])
            ->where('pegawai_id', $pegawaiId)
            ->orderBy('tgl_awal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            // FIX: Panggil helper method
            'pegawai_detail' => $this->formatPegawaiInfo($pegawai),
            'data' => $riwayat->map(function ($item) {
                // FIX: Panggil helper method
                return $this->formatRiwayatHubunganKerjaForDetail($item);
            })
        ]);
    }

    /**
     * Get filter options for the frontend
     * @return array
     */
    public function getFilterOptions() // FIX: This method must be public as it's directly routed
    {
        // Unit Kerja Options - map to 'id' and 'nama' for general dropdowns
        $unitKerjaOptions = SimpegUnitKerja::select('id', 'kode_unit', 'nama_unit', 'parent_unit_id')
                                         ->orderBy('nama_unit')
                                         ->get()
                                         ->map(function($unit) {
                                             return [
                                                 'id' => $unit->kode_unit, // Use kode_unit as ID for filter dropdown
                                                 'nama' => $unit->nama_unit,
                                                 'parent_unit_id' => $unit->parent_unit_id,
                                             ];
                                         })
                                         ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']);

        // Hubungan Kerja Options
        $hubunganKerjaOptions = HubunganKerja::select('id', 'nama_hub_kerja')
                                             ->where('status_aktif', true)
                                             ->orderBy('nama_hub_kerja')
                                             ->get()
                                             ->map(function($hubungan) {
                                                 return [
                                                     'id' => $hubungan->id,
                                                     'nama' => $hubungan->nama_hub_kerja,
                                                 ];
                                             })
                                             ->prepend(['id' => 'semua', 'nama' => 'Semua Hubungan Kerja']);

        // Status Masa Kerja Options
        $statusMasaKerjaOptions = [
            ['id' => 'semua', 'nama' => 'Semua Status'],
            ['id' => 'hampir_berakhir', 'nama' => 'Hampir Berakhir (Kontrak/Masa Jabatan)'],
            ['id' => 'hampir_pensiun', 'nama' => 'Hampir Pensiun (Usia)'],
            ['id' => 'berakhir_sekarang', 'nama' => 'Kontrak Sudah Berakhir'],
            ['id' => 'sudah_pensiun_sekarang', 'nama' => 'Sudah Pensiun'],
        ];

        // Level Filter Options
        $levelFilterOptions = [
            ['id' => 'seuniv', 'nama' => 'Se-Universitas'],
            ['id' => 'sefakultas', 'nama' => 'Se-Fakultas'],
            ['id' => 'seprodi', 'nama' => 'Se-Prodi'],
        ];

        return [
            'unit_kerja' => $unitKerjaOptions,
            'hubungan_kerja' => $hubunganKerjaOptions,
            'status_masa_kerja' => $statusMasaKerjaOptions,
            'level_filter' => $levelFilterOptions,
        ];
    }


    // =========================================================
    // PRIVATE HELPER METHODS (Only called internally within this class)
    // =========================================================

    /**
     * Helper: Format pegawai info (sesuai dengan controller riwayat)
     * @param SimpegPegawai $pegawai
     * @return array
     */
    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) {
            return null;
        }

        $jabatanAkademikNama = '-';
        if ($pegawai->dataJabatanAkademik && $pegawai->dataJabatanAkademik->isNotEmpty()) {
            $jabatanAkademik = $pegawai->dataJabatanAkademik->sortByDesc('tmt_jabatan')->first();
            if ($jabatanAkademik && $jabatanAkademik->jabatanAkademik) {
                $jabatanAkademikNama = $jabatanAkademik->jabatanAkademik->jabatan_akademik ?? '-';
            }
        } else if ($pegawai->jabatanAkademik) {
            $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }

        $jabatanFungsionalNama = '-';
        if ($pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->sortByDesc('tmt_jabatan')->first();
            if ($jabatanFungsional && $jabatanFungsional->jabatanFungsional) {
                $jabatanFungsionalNama = $jabatanFungsional->jabatanFungsional->nama_jabatan_fungsional ?? '-';
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->sortByDesc('tgl_mulai')->first();
            if ($jabatanStruktural && $jabatanStruktural->jabatanStruktural) {
                if ($jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                     $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
                } else {
                     $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan ?? $jabatanStruktural->jabatanStruktural->singkatan ?? '-';
                }
            }
        }

        $jenjangPendidikanNama = '-';
        if ($pegawai->dataPendidikanFormal && $pegawai->dataPendidikanFormal->isNotEmpty()) {
            $highestEducation = $pegawai->dataPendidikanFormal->sortByDesc('jenjang_pendidikan_id')->first();
            if ($highestEducation && $highestEducation->jenjangPendidikan) {
                $jenjangPendidikanNama = $highestEducation->jenjangPendidikan->jenjang_pendidikan ?? '-';
            }
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            // FIX: Menggunakan ID integer dari unit_kerja_id di pegawai untuk lookup di simpeg_unit_kerja.id
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id); // Find by PK (id)
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        $hubunganKerjaNama = '-';
        if ($pegawai->dataHubunganKerja && $pegawai->dataHubunganKerja->isNotEmpty()) {
            $latestHubunganKerja = $pegawai->dataHubunganKerja->sortByDesc('tgl_awal')->first();
            if ($latestHubunganKerja && $latestHubunganKerja->hubunganKerja) {
                $hubunganKerjaNama = $latestHubunganKerja->hubunganKerja->nama_hub_kerja ?? '-';
            }
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_lengkap' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $unitKerjaNama,
            'status_aktif_pegawai' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik_pegawai' => $jabatanAkademikNama,
            'jab_fungsional_pegawai' => $jabatanFungsionalNama,
            'jab_struktural_pegawai' => $jabatanStrukturalNama,
            'pendidikan_terakhir' => $jenjangPendidikanNama,
            'hubungan_kerja_pegawai' => $hubunganKerjaNama,
        ];
    }

    /**
     * Format data hubungan kerja for the main monitoring table (index method).
     * @param SimpegDataHubunganKerja $hubunganKerja
     * @return array
     */
    private function formatMonitoringData($hubunganKerja)
    {
        $pegawai = $hubunganKerja->pegawai;
        $currentDate = Carbon::now();
        
        // Hitung usia
        $usia = null;
        $tanggalLahir = null;
        if ($pegawai && $pegawai->tanggal_lahir) {
            $tanggalLahir = Carbon::parse($pegawai->tanggal_lahir);
            $usia = $tanggalLahir->age;
        }
        
        // Hitung usia pensiun (asumsi pensiun 65 tahun)
        $usiaPensiunInfo = '-';
        $tanggalPensiun = null;
        if ($tanggalLahir) {
            $tanggalPensiun = $tanggalLahir->copy()->addYears(65);
            if ($tanggalPensiun->isPast()) {
                 $usiaPensiunInfo = 'Sudah Pensiun';
            } else {
                 $diffForHumans = $tanggalPensiun->diffForHumans($currentDate, ['syntax' => Carbon::DIFF_RELATIVE_TO_NOW, 'parts' => 2]);
                 $usiaPensiunInfo = 'Pensiun ' . $diffForHumans;
            }
        }
        
        // Format tanggal
        $tglAwal = $hubunganKerja->tgl_awal ? Carbon::parse($hubunganKerja->tgl_awal)->format('d M Y') : '-';
        $tglAkhir = $hubunganKerja->tgl_akhir ? Carbon::parse($hubunganKerja->tgl_akhir)->format('d M Y') : 'Tidak Terbatas';
        $tglLahir = $tanggalLahir ? $tanggalLahir->format('d M Y') : '-';
        
        // Get jabatan fungsional terbaru dari relasi dataJabatanFungsional
        $jabatanFungsionalNama = '-';
        if ($pegawai && $pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $latestFungsional = $pegawai->dataJabatanFungsional->first();
            if ($latestFungsional->jabatanFungsional) {
                $jabatanFungsionalNama = $latestFungsional->jabatanFungsional->nama_jabatan_fungsional ?? '-';
            }
        } else if ($pegawai && $pegawai->jabatanAkademik) {
            $jabatanFungsionalNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }
        
        return [
            'id' => $hubunganKerja->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_pegawai' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . ($pegawai->nama ?? '-') . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'hubungan_kerja' => $hubunganKerja->hubunganKerja->nama_hub_kerja ?? '-',
            'jabatan_fungsional' => $jabatanFungsionalNama,
            'usia' => $usia ?? '-',
            'usia_pensiun' => $usiaPensiunInfo,
            'tanggal_lahir' => $tglLahir,
            'tanggal_efektif' => $tglAwal,
            'tanggal_berakhir' => $tglAkhir,
            'aksi' => ['detail_url' => url("/api/admin/monitoring/hubungan-kerja/{$hubunganKerja->id}")],
        ];
    }

    /**
     * Format riwayat hubungan kerja for detailed view (show and getRiwayatByPegawai methods).
     * @param SimpegDataHubunganKerja $riwayat
     * @return array
     */
    private function formatRiwayatHubunganKerjaForDetail($riwayat)
    {
        return [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => $riwayat->pegawai ? [
                'nip' => $riwayat->pegawai->nip ?? '-',
                'nama' => ($riwayat->pegawai->gelar_depan ? $riwayat->pegawai->gelar_depan . ' ' : '') . ($riwayat->pegawai->nama ?? '-') . ($riwayat->pegawai->gelar_belakang ? ', ' . $riwayat->pegawai->gelar_belakang : '')
            ] : null,
            'tgl_awal' => $riwayat->tgl_awal,
            'tgl_awal_formatted' => $riwayat->tgl_awal ? Carbon::parse($riwayat->tgl_awal)->format('d M Y') : '-',
            'tgl_akhir' => $riwayat->tgl_akhir,
            'tgl_akhir_formatted' => $riwayat->tgl_akhir ? Carbon::parse($riwayat->tgl_akhir)->format('d M Y') : 'Tidak Terbatas',
            'no_sk' => $riwayat->no_sk ?? '-',
            'tgl_sk' => $riwayat->tgl_sk,
            'tgl_sk_formatted' => $riwayat->tgl_sk ? Carbon::parse($riwayat->tgl_sk)->format('d M Y') : '-',
            'pejabat_penetap' => $riwayat->pejabat_penetap ?? '-',
            'hubungan_kerja' => $riwayat->hubunganKerja ? [
                'id' => $riwayat->hubunganKerja->id,
                'nama' => $riwayat->hubunganKerja->nama_hub_kerja
            ] : null,
            'status_aktif' => $riwayat->statusAktif ? [
                'id' => $riwayat->statusAktif->id,
                'nama' => $riwayat->statusAktif->nama_status_aktif
            ] : null,
            'status' => [
                'is_aktif' => $riwayat->is_aktif ?? false,
                'pengajuan' => $riwayat->status_pengajuan ?? 'draft'
            ],
            'dokumen' => $riwayat->file_hubungan_kerja ? [
                'nama_file' => basename($riwayat->file_hubungan_kerja),
                'url' => \Storage::url($riwayat->file_hubungan_kerja),
                'download_url' => url("/api/admin/monitoring/hubungan-kerja/{$riwayat->id}/download")
            ] : null,
            'tgl_input' => $riwayat->tgl_input,
            'tgl_input_formatted' => $riwayat->tgl_input ? Carbon::parse($riwayat->tgl_input)->format('d M Y') : '-',
            'tgl_diajukan' => $riwayat->tgl_diajukan,
            'tgl_diajukan_formatted' => $riwayat->tgl_diajukan ? Carbon::parse($riwayat->tgl_diajukan)->format('d M Y H:i:s') : '-',
            'tgl_disetujui' => $riwayat->tgl_disetujui,
            'tgl_disetujui_formatted' => $riwayat->tgl_disetujui ? Carbon::parse($riwayat->tgl_disetujui)->format('d M Y H:i:s') : '-',
            'tgl_ditolak' => $riwayat->tgl_ditolak,
            'tgl_ditolak_formatted' => $riwayat->tgl_ditolak ? Carbon::parse($riwayat->tgl_ditolak)->format('d M Y H:i:s') : '-',
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];
    }

    /**
     * Calculate masa kerja dari riwayat hubungan kerja
     * @param SimpegDataHubunganKerja $riwayat
     * @return array
     */
    private function calculateMasaKerja($riwayat)
    {
        if (!$riwayat->tgl_awal) {
            return [
                'tahun' => 0,
                'bulan' => 0,
                'hari' => 0,
                'total_hari' => 0,
                'formatted' => '-'
            ];
        }

        $tglMulai = Carbon::parse($riwayat->tgl_awal);
        $tglSelesai = $riwayat->tgl_akhir ? Carbon::parse($riwayat->tgl_akhir) : Carbon::now();

        if ($tglMulai > Carbon::now()) {
            return [
                'tahun' => 0,
                'bulan' => 0,
                'hari' => 0,
                'total_hari' => 0,
                'formatted' => 'Belum Dimulai',
                'status' => 'belum_mulai'
            ];
        }

        $diff = $tglMulai->diff($tglSelesai);
        $totalHari = $tglMulai->diffInDays($tglSelesai);

        $formatted = '';
        if ($diff->y > 0) {
            $formatted .= $diff->y . ' tahun ';
        }
        if ($diff->m > 0) {
            $formatted .= $diff->m . ' bulan ';
        }
        if ($diff->d > 0) {
            $formatted .= $diff->d . ' hari';
        }

        if (empty(trim($formatted))) {
            $formatted = '0 hari';
        }

        return [
            'tahun' => $diff->y,
            'bulan' => $diff->m,
            'hari' => $diff->d,
            'total_hari' => $totalHari,
            'formatted' => trim($formatted),
            'status' => $riwayat->tgl_akhir && Carbon::parse($riwayat->tgl_akhir)->isPast() ? 'selesai' : 'aktif'
        ];
    }

    /**
     * Get timeline status pengajuan
     * @param SimpegDataHubunganKerja $riwayat
     * @return array
     */
    private function getStatusTimeline($riwayat)
    {
        $timeline = [];

        $statusPengajuan = $riwayat->status_pengajuan ?? 'draft';

        $timeline[] = [
            'status' => 'draft',
            'label' => 'Draft',
            'tanggal' => $riwayat->created_at ? $riwayat->created_at->format('d-m-Y H:i') : '-',
            'is_current' => $statusPengajuan === 'draft',
            'is_completed' => in_array($statusPengajuan, ['diajukan', 'disetujui', 'ditolak']),
            'icon' => 'edit',
            'color' => 'gray'
        ];

        if ($riwayat->tgl_diajukan || in_array($statusPengajuan, ['diajukan', 'disetujui', 'ditolak'])) {
            $timeline[] = [
                'status' => 'diajukan',
                'label' => 'Diajukan',
                'tanggal' => $riwayat->tgl_diajukan ? Carbon::parse($riwayat->tgl_diajukan)->format('d-m-Y H:i') : '-',
                'is_current' => $statusPengajuan === 'diajukan',
                'is_completed' => in_array($statusPengajuan, ['disetujui', 'ditolak']),
                'icon' => 'send',
                'color' => 'blue'
            ];
        }


        if ($statusPengajuan === 'disetujui') {
            $timeline[] = [
                'status' => 'disetujui',
                'label' => 'Disetujui',
                'tanggal' => $riwayat->tgl_disetujui ? Carbon::parse($riwayat->tgl_disetujui)->format('d-m-Y H:i') : '-',
                'is_current' => true,
                'is_completed' => true,
                'icon' => 'check',
                'color' => 'green'
            ];
        }

        if ($statusPengajuan === 'ditolak') {
            $timeline[] = [
                'status' => 'ditolak',
                'label' => 'Ditolak',
                'tanggal' => $riwayat->tgl_ditolak ? Carbon::parse($riwayat->tgl_ditolak)->format('d-m-Y H:i') : '-',
                'is_current' => true,
                'is_completed' => true,
                'icon' => 'x',
                'color' => 'red'
            ];
        }

        return $timeline;
    }

    /**
     * Dapatkan unit kerja kode (string) berdasarkan level filter.
     * @param string $unitKerjaId - Ini adalah kode_unit (string) dari SimpegUnitKerja
     * @param string $level
     * @return array - Mengembalikan array ID (integer) dari SimpegUnitKerja
     */
    private function getUnitKerjaIdsByLevel($unitKerjaId, $level)
    {
        // Temukan unit kerja berdasarkan kode_unit (string)
        $unitKerja = SimpegUnitKerja::where('kode_unit', $unitKerjaId)->first();
        
        if (!$unitKerja) {
            return [];
        }

        $unitIds = [];

        switch ($level) {
            case 'seuniv':
                $unitIds = SimpegUnitKerja::pluck('id')->toArray();
                break;
                
            case 'sefakultas':
                $unitIds[] = $unitKerja->id;
                $childrenIds = $unitKerja->children()->pluck('id')->toArray();
                $unitIds = array_merge($unitIds, $childrenIds);

                if ($unitKerja->parent_unit_id && $unitKerja->parent_unit_id != '041001') { 
                    $parentFakultas = SimpegUnitKerja::where('kode_unit', $unitKerja->parent_unit_id)->first();
                    if ($parentFakultas) {
                        $unitIds[] = $parentFakultas->id;
                        $siblingsIds = $parentFakultas->children()->pluck('id')->toArray();
                        $unitIds = array_merge($unitIds, $siblingsIds);
                    }
                }
                break;
                
            case 'seprodi':
                $unitIds = [$unitKerja->id];
                break;
                
            default:
                $unitIds = [$unitKerja->id];
                break;
        }

        return array_unique($unitIds);
    }

    /**
     * Apply filter berdasarkan status masa kerja
     * @param Builder $query
     * @param string $statusFilter
     * @return Builder
     */
    private function applyStatusMasaKerjaFilter(Builder $query, $statusFilter)
    {
        $currentDate = Carbon::now();
        
        switch ($statusFilter) {
            case 'hampir_berakhir':
                $sixMonthsLater = $currentDate->copy()->addMonths(6);
                $query->where(function($q) use ($currentDate, $sixMonthsLater) {
                    $q->whereNotNull('tgl_akhir')
                      ->whereDate('tgl_akhir', '>', $currentDate->format('Y-m-d'))
                      ->whereDate('tgl_akhir', '<=', $sixMonthsLater->format('Y-m-d'));
                });
                break;
                
            case 'hampir_pensiun':
                $twoYearsLater = $currentDate->copy()->addYears(2);
                
                $connection = config('database.default');
                $driver = config("database.connections.{$connection}.driver");
                
                if ($driver === 'pgsql') {
                    $query->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                        $q->whereNotNull('tanggal_lahir')
                          ->whereRaw("(tanggal_lahir + INTERVAL '65 years')::date BETWEEN ? AND ?", 
                                     [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                    });
                } else {
                    $query->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                        $q->whereNotNull('tanggal_lahir')
                          ->whereRaw("DATE_ADD(tanggal_lahir, INTERVAL 65 YEAR) BETWEEN ? AND ?", 
                                     [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                    });
                }
                break;
            case 'berakhir_sekarang':
                $query->whereNotNull('tgl_akhir')->whereDate('tgl_akhir', '<=', $currentDate->format('Y-m-d'));
                break;
            case 'sudah_pensiun_sekarang':
                $connection = config('database.default');
                $driver = config("database.connections.{$connection}.driver");
                if ($driver === 'pgsql') {
                    $query->whereHas('pegawai', function($q) use ($currentDate) {
                        $q->whereNotNull('tanggal_lahir')
                          ->whereRaw("(tanggal_lahir + INTERVAL '65 years')::date <= ?", [$currentDate->format('Y-m-d')]);
                    });
                } else {
                    $query->whereHas('pegawai', function($q) use ($currentDate) {
                        $q->whereNotNull('tanggal_lahir')
                          ->whereRaw("DATE_ADD(tanggal_lahir, INTERVAL 65 YEAR) <= ?", [$currentDate->format('Y-m-d')]);
                    });
                }
                break;
        }
        
        return $query;
    }

    /**
     * Get summary statistics
     * @param string|null $unitKerjaFilter
     * @param string $levelFilter
     * @param string|null $hubunganKerjaFilter
     * @param string|null $statusMasaKerjaFilter
     * @return array
     */
    private function getSummaryStatistics($unitKerjaFilter = null, $levelFilter = 'seuniv', $hubunganKerjaFilter = null, $statusMasaKerjaFilter = null)
    {
        $baseQuery = SimpegDataHubunganKerja::where('is_aktif', true);
        
        if ($unitKerjaFilter && $unitKerjaFilter !== 'semua') {
            $unitKerjaIds = $this->getUnitKerjaIdsByLevel($unitKerjaFilter, $levelFilter);
            $baseQuery->whereHas('pegawai', function($q) use ($unitKerjaIds) {
                $q->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }
        
        if ($hubunganKerjaFilter && $hubunganKerjaFilter !== 'semua') {
            $baseQuery->where('hubungan_kerja_id', $hubunganKerjaFilter);
        }

        $totalHampirBerakhirQuery = clone $baseQuery;
        $totalHampirPensiunQuery = clone $baseQuery;

        $total = $baseQuery->count();
        
        $statsHubunganKerja = $baseQuery->clone()
                                         ->join('simpeg_hubungan_kerja', 'simpeg_data_hubungan_kerja.hubungan_kerja_id', '=', 'simpeg_hubungan_kerja.id')
                                         ->groupBy('simpeg_hubungan_kerja.nama_hub_kerja')
                                         ->selectRaw('simpeg_hubungan_kerja.nama_hub_kerja, COUNT(*) as jumlah')
                                         ->get();

        $currentDate = Carbon::now();
        $sixMonthsLater = $currentDate->copy()->addMonths(6);
        $twoYearsLater = $currentDate->copy()->addYears(2);
        
        $hampirBerakhir = $totalHampirBerakhirQuery
                                     ->whereNotNull('tgl_akhir')
                                     ->whereDate('tgl_akhir', '>', $currentDate->format('Y-m-d'))
                                     ->whereDate('tgl_akhir', '<=', $sixMonthsLater->format('Y-m-d'))
                                     ->count();
        
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        if ($driver === 'pgsql') {
            $hampirPensiun = $totalHampirPensiunQuery
                                            ->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                                                $q->whereNotNull('tanggal_lahir')
                                                  ->whereRaw("(tanggal_lahir + INTERVAL '65 years')::date BETWEEN ? AND ?", 
                                                             [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                                            })
                                            ->count();
        } else {
            $hampirPensiun = $totalHampirPensiunQuery
                                            ->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                                                $q->whereNotNull('tanggal_lahir')
                                                  ->whereRaw("DATE_ADD(tanggal_lahir, INTERVAL 65 YEAR) BETWEEN ? AND ?", 
                                                             [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                                            })
                                            ->count();
        }

        return [
            'total_pegawai' => $total,
            'hampir_berakhir_kontrak' => $hampirBerakhir,
            'hampir_pensiun_usia' => $hampirPensiun,
            'distribusi_hubungan_kerja' => $statsHubunganKerja,
        ];
    }
}