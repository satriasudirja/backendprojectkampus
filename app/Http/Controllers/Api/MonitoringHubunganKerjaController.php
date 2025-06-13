<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataHubunganKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\HubunganKerja;
use App\Models\SimpegJabatanFungsional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MonitoringHubunganKerjaController extends Controller
{
    /**
     * Monitoring hubungan kerja dengan filter unit kerja, status masa kerja, dan hubungan kerja
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        
        // Filter parameters
        $unitKerjaFilter = $request->unit_kerja; // ID unit kerja
        $statusMasaKerjaFilter = $request->status_masa_kerja; // hampir_berakhir, hampir_pensiun
        $hubunganKerjaFilter = $request->hubungan_kerja; // ID hubungan kerja
        $levelFilter = $request->level ?? 'seuniv'; // seuniv, sefakultas, seprodi

        // Base query - ambil hubungan kerja yang aktif
        $query = SimpegDataHubunganKerja::with([
            'pegawai.unitKerja',
            'pegawai.jabatanAkademik', 
            'hubunganKerja',
            'statusAktif'
        ])->where('is_aktif', true);

        // Filter berdasarkan unit kerja dan level
        if ($unitKerjaFilter) {
            $unitKerjaIds = $this->getUnitKerjaIdsByLevel($unitKerjaFilter, $levelFilter);
            
            $query->whereHas('pegawai', function($q) use ($unitKerjaIds) {
                $q->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        // Filter berdasarkan jenis hubungan kerja
        if ($hubunganKerjaFilter) {
            $query->where('hubungan_kerja_id', $hubunganKerjaFilter);
        }

        // Filter berdasarkan status masa kerja
        if ($statusMasaKerjaFilter) {
            $query = $this->applyStatusMasaKerjaFilter($query, $statusMasaKerjaFilter);
        }

        // Search functionality
        if ($search) {
            $query->where(function($q) use ($search) {
                // Search by NIP
                $q->whereHas('pegawai', function($subQ) use ($search) {
                    $subQ->where('nip', 'like', '%'.$search.'%');
                })
                // Search by nama pegawai
                ->orWhereHas('pegawai', function($subQ) use ($search) {
                    $subQ->where('nama', 'like', '%'.$search.'%');
                })
                // Search by unit kerja
                ->orWhereHas('pegawai.unitKerja', function($subQ) use ($search) {
                    $subQ->where('nama_unit', 'like', '%'.$search.'%');
                })
                // Search by jenis hubungan kerja
                ->orWhereHas('hubunganKerja', function($subQ) use ($search) {
                    $subQ->where('nama_hub_kerja', 'like', '%'.$search.'%');
                })
                // Search by nomor SK
                ->orWhere('no_sk', 'like', '%'.$search.'%');
            });
        }

        // Order by nama pegawai
        $query->join('simpeg_pegawai', 'simpeg_data_hubungan_kerja.pegawai_id', '=', 'simpeg_pegawai.id')
              ->orderBy('simpeg_pegawai.nama', 'asc')
              ->select('simpeg_data_hubungan_kerja.*');

        $hubunganKerjaData = $query->paginate($perPage);

        // Get summary statistics
        $summaryStats = $this->getSummaryStatistics($unitKerjaFilter, $levelFilter, $hubunganKerjaFilter, $statusMasaKerjaFilter);

        // Get filter options
        $filterOptions = $this->getFilterOptions();

        return response()->json([
            'success' => true,
            'summary' => $summaryStats,
            'filter_options' => $filterOptions,
            'data' => $hubunganKerjaData->map(function ($item) {
                return $this->formatHubunganKerjaData($item);
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
            ]
        ]);
    }

    /**
     * Download file hubungan kerja
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

        return response()->download($filePath);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Helper: Format pegawai info (sesuai dengan controller riwayat)
     */
    private function formatPegawaiInfo($pegawai)
    {
        $jabatanAkademikNama = '-';
        if ($pegawai->jabatanAkademik) {
            $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }

        $jabatanFungsionalNama = '-';
        if ($pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                if (isset($jabatanFungsional->nama_jabatan_fungsional)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional;
                } elseif (isset($jabatanFungsional->nama)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama;
                }
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
            
            if ($jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->singkatan;
            }
            elseif (isset($jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->nama_jabatan;
            }
        }

        $jenjangPendidikanNama = '-';
        if ($pegawai->dataPendidikanFormal && $pegawai->dataPendidikanFormal->isNotEmpty()) {
            $highestEducation = $pegawai->dataPendidikanFormal->first();
            if ($highestEducation && $highestEducation->jenjangPendidikan) {
                $jenjangPendidikanNama = $highestEducation->jenjangPendidikan->jenjang_pendidikan ?? '-';
            }
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => $unitKerjaNama,
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama,
            'jab_struktural' => $jabatanStrukturalNama,
            'pendidikan' => $jenjangPendidikanNama
        ];
    }

    /**
     * Format riwayat hubungan kerja (sesuai dengan controller riwayat)
     */
    private function formatRiwayatHubunganKerja($riwayat)
    {
        return [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => $riwayat->pegawai ? [
                'nip' => $riwayat->pegawai->nip ?? '-',
                'nama' => $riwayat->pegawai->nama ?? '-'
            ] : null,
            'tgl_awal' => $riwayat->tgl_awal,
            'tgl_awal_formatted' => $riwayat->tgl_awal ? Carbon::parse($riwayat->tgl_awal)->format('d-m-Y') : '-',
            'tgl_akhir' => $riwayat->tgl_akhir,
            'tgl_akhir_formatted' => $riwayat->tgl_akhir ? Carbon::parse($riwayat->tgl_akhir)->format('d-m-Y') : '-',
            'no_sk' => $riwayat->no_sk ?? '-',
            'tgl_sk' => $riwayat->tgl_sk,
            'tgl_sk_formatted' => $riwayat->tgl_sk ? Carbon::parse($riwayat->tgl_sk)->format('d-m-Y') : '-',
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
            'tgl_input_formatted' => $riwayat->tgl_input ? Carbon::parse($riwayat->tgl_input)->format('d-m-Y') : '-',
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];
    }

    /**
     * Dapatkan unit kerja IDs berdasarkan level filter
     */
    private function getUnitKerjaIdsByLevel($unitKerjaId, $level)
    {
        $unitKerja = SimpegUnitKerja::find($unitKerjaId);
        if (!$unitKerja) {
            return [$unitKerjaId];
        }

        $unitKerjaIds = [];

        switch ($level) {
            case 'seuniv':
                // Ambil semua unit kerja di universitas
                $unitKerjaIds = SimpegUnitKerja::pluck('kode_unit')->toArray();
                break;
                
            case 'sefakultas':
                // Ambil unit kerja satu fakultas (parent + children)
                $unitKerjaIds = [$unitKerja->kode_unit];
                
                // Jika ini fakultas, ambil semua prodi di bawahnya
                if ($unitKerja->parent_unit_id == '041001') { // Parent adalah universitas
                    $children = SimpegUnitKerja::where('parent_unit_id', $unitKerja->kode_unit)
                                              ->pluck('kode_unit')
                                              ->toArray();
                    $unitKerjaIds = array_merge($unitKerjaIds, $children);
                }
                // Jika ini prodi, ambil juga fakultas parentnya
                else if ($unitKerja->parent_unit_id && $unitKerja->parent_unit_id != '041001') {
                    $parent = SimpegUnitKerja::where('kode_unit', $unitKerja->parent_unit_id)->first();
                    if ($parent) {
                        $unitKerjaIds[] = $parent->kode_unit;
                        // Ambil semua prodi saudara
                        $siblings = SimpegUnitKerja::where('parent_unit_id', $parent->kode_unit)
                                                  ->pluck('kode_unit')
                                                  ->toArray();
                        $unitKerjaIds = array_merge($unitKerjaIds, $siblings);
                    }
                }
                break;
                
            case 'seprodi':
                // Hanya unit kerja yang dipilih
                $unitKerjaIds = [$unitKerja->kode_unit];
                break;
                
            default:
                $unitKerjaIds = [$unitKerja->kode_unit];
                break;
        }

        return array_unique($unitKerjaIds);
    }

    /**
     * Apply filter berdasarkan status masa kerja
     */
    private function applyStatusMasaKerjaFilter($query, $statusFilter)
    {
        $currentDate = Carbon::now();
        
        switch ($statusFilter) {
            case 'hampir_berakhir':
                // Masa kerja berakhir dalam 6 bulan ke depan
                $sixMonthsLater = $currentDate->copy()->addMonths(6);
                $query->where(function($q) use ($currentDate, $sixMonthsLater) {
                    $q->whereNotNull('tgl_akhir')
                      ->whereDate('tgl_akhir', '>', $currentDate)
                      ->whereDate('tgl_akhir', '<=', $sixMonthsLater);
                });
                break;
                
            case 'hampir_pensiun':
                // Pegawai yang akan pensiun dalam 2 tahun (asumsi pensiun usia 65)
                $twoYearsLater = $currentDate->copy()->addYears(2);
                
                // Deteksi database type untuk menggunakan syntax yang tepat
                $connection = config('database.default');
                $driver = config("database.connections.{$connection}.driver");
                
                if ($driver === 'pgsql') {
                    // PostgreSQL syntax
                    $query->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                        $q->whereNotNull('tanggal_lahir')
                          ->whereRaw("(tanggal_lahir + INTERVAL '65 years')::date BETWEEN ? AND ?", 
                                    [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                    });
                } else {
                    // MySQL syntax
                    $query->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                        $q->whereNotNull('tanggal_lahir')
                          ->whereRaw("DATE_ADD(tanggal_lahir, INTERVAL 65 YEAR) BETWEEN ? AND ?", 
                                    [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                    });
                }
                break;
        }
        
        return $query;
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStatistics($unitKerja = null, $level = 'seuniv', $hubunganKerja = null, $statusMasaKerja = null)
    {
        $baseQuery = SimpegDataHubunganKerja::where('is_aktif', true);
        
        // Apply same filters as main query
        if ($unitKerja) {
            $unitKerjaIds = $this->getUnitKerjaIdsByLevel($unitKerja, $level);
            $baseQuery->whereHas('pegawai', function($q) use ($unitKerjaIds) {
                $q->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }
        
        if ($hubunganKerja) {
            $baseQuery->where('hubungan_kerja_id', $hubunganKerja);
        }

        $total = $baseQuery->count();
        
        // Statistik berdasarkan jenis hubungan kerja
        $statsHubunganKerja = $baseQuery->clone()
                                       ->join('simpeg_hubungan_kerja', 'simpeg_data_hubungan_kerja.hubungan_kerja_id', '=', 'simpeg_hubungan_kerja.id')
                                       ->groupBy('simpeg_hubungan_kerja.nama_hub_kerja')
                                       ->selectRaw('simpeg_hubungan_kerja.nama_hub_kerja, COUNT(*) as jumlah')
                                       ->get();

        // Statistik masa kerja
        $currentDate = Carbon::now();
        $sixMonthsLater = $currentDate->copy()->addMonths(6);
        $twoYearsLater = $currentDate->copy()->addYears(2);
        
        $hampirBerakhir = $baseQuery->clone()
                                  ->whereNotNull('tgl_akhir')
                                  ->whereDate('tgl_akhir', '>', $currentDate)
                                  ->whereDate('tgl_akhir', '<=', $sixMonthsLater)
                                  ->count();
        
        // Deteksi database type untuk query hampir pensiun
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        if ($driver === 'pgsql') {
            // PostgreSQL syntax
            $hampirPensiun = $baseQuery->clone()
                                     ->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                                         $q->whereNotNull('tanggal_lahir')
                                           ->whereRaw("(tanggal_lahir + INTERVAL '65 years')::date BETWEEN ? AND ?", 
                                                     [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                                     })
                                     ->count();
        } else {
            // MySQL syntax
            $hampirPensiun = $baseQuery->clone()
                                     ->whereHas('pegawai', function($q) use ($currentDate, $twoYearsLater) {
                                         $q->whereNotNull('tanggal_lahir')
                                           ->whereRaw("DATE_ADD(tanggal_lahir, INTERVAL 65 YEAR) BETWEEN ? AND ?", 
                                                     [$currentDate->format('Y-m-d'), $twoYearsLater->format('Y-m-d')]);
                                     })
                                     ->count();
        }

        return [
            'total_pegawai' => $total,
            'hampir_berakhir' => $hampirBerakhir,
            'hampir_pensiun' => $hampirPensiun,
            'distribusi_hubungan_kerja' => $statsHubunganKerja,
        ];
    }

    /**
     * Get filter options
     */
    private function getFilterOptions()
    {
        return [
            'unit_kerja' => SimpegUnitKerja::select('id', 'kode_unit', 'nama_unit', 'parent_unit_id')
                                          ->orderBy('nama_unit')
                                          ->get()
                                          ->map(function($unit) {
                                              return [
                                                  'id' => $unit->id,
                                                  'kode_unit' => $unit->kode_unit,
                                                  'nama_unit' => $unit->nama_unit,
                                                  'parent_unit_id' => $unit->parent_unit_id,
                                                  'value' => $unit->id,
                                                  'label' => $unit->nama_unit
                                              ];
                                          }),
            'hubungan_kerja' => HubunganKerja::select('id', 'kode', 'nama_hub_kerja')
                                           ->where('status_aktif', true)
                                           ->orderBy('nama_hub_kerja')
                                           ->get()
                                           ->map(function($hubungan) {
                                               return [
                                                   'id' => $hubungan->id,
                                                   'kode' => $hubungan->kode,
                                                   'nama_hub_kerja' => $hubungan->nama_hub_kerja,
                                                   'value' => $hubungan->id,
                                                   'label' => $hubungan->nama_hub_kerja
                                               ];
                                           }),
            'status_masa_kerja' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => 'hampir_berakhir', 'label' => 'Hampir Berakhir (6 Bulan)'],
                ['value' => 'hampir_pensiun', 'label' => 'Hampir Pensiun (2 Tahun)']
            ],
            'level_filter' => [
                ['value' => 'seuniv', 'label' => 'Se-Universitas'],
                ['value' => 'sefakultas', 'label' => 'Se-Fakultas'],
                ['value' => 'seprodi', 'label' => 'Se-Prodi']
            ]
        ];
    }

    /**
     * Format data hubungan kerja untuk tampilan tabel monitoring
     */
    private function formatHubunganKerjaData($hubunganKerja)
    {
        $pegawai = $hubunganKerja->pegawai;
        $currentDate = Carbon::now();
        
        // Hitung usia
        $usia = null;
        $tanggalLahir = null;
        if ($pegawai->tanggal_lahir) {
            $tanggalLahir = Carbon::parse($pegawai->tanggal_lahir);
            $usia = $tanggalLahir->age;
        }
        
        // Hitung usia pensiun (asumsi pensiun 65 tahun)
        $usiapensiun = null;
        $tanggalPensiun = null;
        if ($tanggalLahir) {
            $tanggalPensiun = $tanggalLahir->copy()->addYears(65);
            $usiapensiun = 65 - $usia;
        }
        
        // Format tanggal
        $tglAwal = $hubunganKerja->tgl_awal ? Carbon::parse($hubunganKerja->tgl_awal)->format('d M Y') : '-';
        $tglAkhir = $hubunganKerja->tgl_akhir ? Carbon::parse($hubunganKerja->tgl_akhir)->format('d M Y') : 'Tidak Terbatas';
        $tglLahir = $tanggalLahir ? $tanggalLahir->format('d M Y') : '-';
        
        // Get jabatan fungsional terbaru
        $jabatanFungsional = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        
        // Status badge untuk masa kerja
        $statusBadge = '';
        if ($hubunganKerja->tgl_akhir) {
            $tglAkhirCarbon = Carbon::parse($hubunganKerja->tgl_akhir);
            $daysDiff = $currentDate->diffInDays($tglAkhirCarbon, false);
            
            if ($daysDiff <= 180 && $daysDiff > 0) { // Kurang dari 6 bulan
                $statusBadge = 'hampir-berakhir';
            } elseif ($daysDiff <= 0) {
                $statusBadge = 'berakhir';
            }
        }
        
        // Status badge untuk pensiun
        $statusPensiunBadge = '';
        if ($usiapensiun !== null && $usiapensiun <= 2 && $usiapensiun > 0) {
            $statusPensiunBadge = 'hampir-pensiun';
        } elseif ($usiapensiun !== null && $usiapensiun <= 0) {
            $statusPensiunBadge = 'sudah-pensiun';
        }
        
        return [
            'id' => $hubunganKerja->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_pegawai' => $pegawai->nama ?? '-',
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'hubungan_kerja' => $hubunganKerja->hubunganKerja->nama_hub_kerja ?? '-',
            'jabatan_fungsional' => $jabatanFungsional,
            'usia' => $usia ?? '-',
            'usia_pensiun' => $usiapensiun && $usiapensiun > 0 ? $usiapensiun . ' tahun lagi' : ($usiapensiun <= 0 ? 'Sudah Pensiun' : '-'),
            'tanggal_lahir' => $tglLahir,
            'tanggal_efektif' => $tglAwal,
            'tanggal_berakhir' => $tglAkhir,
            'status_badge' => $statusBadge,
            'status_pensiun_badge' => $statusPensiunBadge,
            'detail' => [
                'no_sk' => $hubunganKerja->no_sk ?? '-',
                'tgl_sk' => $hubunganKerja->tgl_sk ? Carbon::parse($hubunganKerja->tgl_sk)->format('d M Y') : '-',
                'pejabat_penetap' => $hubunganKerja->pejabat_penetap ?? '-',
                'status_pengajuan' => $hubunganKerja->status_pengajuan_label ?? $hubunganKerja->status_pengajuan ?? 'Draft',
                'file_hubungan_kerja' => $hubunganKerja->file_hubungan_kerja ? [
                    'nama_file' => basename($hubunganKerja->file_hubungan_kerja),
                    'url' => \Storage::url($hubunganKerja->file_hubungan_kerja),
                    'download_url' => url("/api/admin/monitoring/hubungan-kerja/{$hubunganKerja->id}/download")
                ] : null,
                'keterangan' => $hubunganKerja->keterangan ?? '-'
            ]
        ];
    }

    /**
     * Get detail hubungan kerja by ID dengan riwayat lengkap
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
                        $q->with('jabatanFungsional')
                          ->orderBy('tmt_jabatan', 'desc')
                          ->limit(1);
                    },
                    'dataJabatanStruktural' => function($q) {
                        $q->with('jabatanStruktural.jenisJabatanStruktural')
                          ->orderBy('tgl_mulai', 'desc')
                          ->limit(1);
                    },
                    'dataPendidikanFormal' => function($q) {
                        $q->with('jenjangPendidikan')
                          ->orderBy('jenjang_pendidikan_id', 'desc')
                          ->limit(1);
                    }
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

        // Ambil semua riwayat hubungan kerja untuk pegawai ini
        $riwayatHubunganKerja = SimpegDataHubunganKerja::with(['hubunganKerja', 'statusAktif'])
            ->where('pegawai_id', $hubunganKerja->pegawai_id)
            ->orderBy('tgl_awal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatHubunganKerja($hubunganKerja),
            'pegawai' => $this->formatPegawaiInfo($hubunganKerja->pegawai),
            'riwayat_hubungan_kerja' => $riwayatHubunganKerja->map(function($item) {
                return $this->formatRiwayatHubunganKerja($item);
            })
        ]);
    }

    /**
     * Get semua riwayat hubungan kerja dari pegawai tertentu
     */
    public function getRiwayatByPegawai($pegawaiId)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja',
            'statusAktif', 
            'jabatanAkademik',
            'dataJabatanFungsional' => function($query) {
                $query->with('jabatanFungsional')
                      ->orderBy('tmt_jabatan', 'desc')
                      ->limit(1);
            },
            'dataJabatanStruktural' => function($query) {
                $query->with('jabatanStruktural.jenisJabatanStruktural')
                      ->orderBy('tgl_mulai', 'desc')
                      ->limit(1);
            },
            'dataPendidikanFormal' => function($query) {
                $query->with('jenjangPendidikan')
                      ->orderBy('jenjang_pendidikan_id', 'desc')
                      ->limit(1);
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
            'pegawai' => $this->formatPegawaiInfo($pegawai),
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatHubunganKerja($item);
            })
        ]);
    }

    /**
     * Get detail riwayat hubungan kerja (endpoint khusus untuk detail lengkap)
     */
    public function getDetailRiwayat($id)
    {
        $riwayat = SimpegDataHubunganKerja::with([
            'pegawai' => function($query) {
                $query->with([
                    'unitKerja',
                    'statusAktif', 
                    'jabatanAkademik',
                    'dataJabatanFungsional' => function($q) {
                        $q->with('jabatanFungsional')
                          ->orderBy('tmt_jabatan', 'desc')
                          ->limit(1);
                    },
                    'dataJabatanStruktural' => function($q) {
                        $q->with('jabatanStruktural.jenisJabatanStruktural')
                          ->orderBy('tgl_mulai', 'desc')
                          ->limit(1);
                    },
                    'dataPendidikanFormal' => function($q) {
                        $q->with('jenjangPendidikan')
                          ->orderBy('jenjang_pendidikan_id', 'desc')
                          ->limit(1);
                    }
                ]);
            },
            'hubunganKerja',
            'statusAktif'
        ])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat hubungan kerja tidak ditemukan'
            ], 404);
        }

        // Ambil riwayat hubungan kerja lainnya untuk pegawai yang sama (excluding current)
        $riwayatLainnya = SimpegDataHubunganKerja::with(['hubunganKerja', 'statusAktif'])
            ->where('pegawai_id', $riwayat->pegawai_id)
            ->where('id', '!=', $id)
            ->orderBy('tgl_awal', 'desc')
            ->get();

        // Hitung masa kerja
        $masaKerja = $this->calculateMasaKerja($riwayat);

        // Status timeline
        $timeline = $this->getStatusTimeline($riwayat);

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatHubunganKerja($riwayat),
            'pegawai' => $this->formatPegawaiInfo($riwayat->pegawai),
            'masa_kerja' => $masaKerja,
            'timeline' => $timeline,
            'riwayat_lainnya' => $riwayatLainnya->map(function($item) {
                return $this->formatRiwayatHubunganKerja($item);
            }),
            'metadata' => [
                'total_riwayat' => $riwayatLainnya->count() + 1,
                'status_aktif' => $riwayat->is_aktif ?? false,
                'dapat_diubah' => in_array($riwayat->status_pengajuan ?? 'draft', ['draft', 'ditolak']),
                'memiliki_dokumen' => !empty($riwayat->file_hubungan_kerja)
            ]
        ]);
    }

    /**
     * Calculate masa kerja dari riwayat hubungan kerja
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

        // Jika masa kerja belum dimulai
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
            'status' => $riwayat->tgl_akhir && Carbon::parse($riwayat->tgl_akhir) < Carbon::now() ? 'selesai' : 'aktif'
        ];
    }

    /**
     * Get timeline status pengajuan
     */
    private function getStatusTimeline($riwayat)
    {
        $timeline = [];

        // Timeline berdasarkan status pengajuan
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

        if (in_array($statusPengajuan, ['diajukan', 'disetujui', 'ditolak'])) {
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
}