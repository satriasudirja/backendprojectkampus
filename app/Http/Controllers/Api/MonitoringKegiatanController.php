<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MonitoringKegiatanController extends Controller
{
    /**
     * Monitoring kegiatan dengan filter dan search
     * Default menampilkan data hari ini
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        
        // Filter parameters - default hari ini
        $tanggal = $request->tanggal ?? date('Y-m-d');
        $unitKerjaFilter = $request->unit_kerja;

        // Pastikan ada data absensi untuk hari yang dipilih
        $this->generateDailyAttendanceRecords($tanggal);

        // Base query dengan relasi - focus pada data yang ada kegiatan
        $query = SimpegAbsensiRecord::with([
            'pegawai.unitKerja',
            'jamKerja',
        ])->whereDate('tanggal_absensi', $tanggal)
          ->where(function($q) {
              // Hanya tampilkan yang ada jam masuk (sudah absen)
              $q->whereNotNull('jam_masuk');
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
                // Search by rencana kegiatan
                ->orWhere('rencana_kegiatan', 'like', '%'.$search.'%')
                // Search by realisasi kegiatan
                ->orWhere('realisasi_kegiatan', 'like', '%'.$search.'%')
                // Search by unit kerja
                ->orWhereHas('pegawai', function($subQ) use ($search) {
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM simpeg_unit_kerja 
                        WHERE simpeg_pegawai.unit_kerja_id::text = simpeg_unit_kerja.kode_unit 
                        AND LOWER(simpeg_unit_kerja.nama_unit) LIKE LOWER(?) 
                        AND simpeg_unit_kerja.deleted_at IS NULL
                    )", ['%'.$search.'%']);
                });
            });
        }

        // Order by jam masuk
        $query->orderBy('jam_masuk', 'asc');

        $kegiatanData = $query->paginate($perPage);

        // Get summary statistics untuk hari ini
        $summaryStats = $this->getSummaryStatistics($tanggal, $unitKerjaFilter);

        // Get filter options
        $filterOptions = $this->getFilterOptions();

        return response()->json([
            'success' => true,
            'tanggal_monitoring' => $tanggal,
            'hari' => Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, DD MMMM YYYY'),
            'summary' => $summaryStats,
            'filter_options' => $filterOptions,
            'data' => $kegiatanData->map(function ($item) {
                return $this->formatKegiatanData($item);
            }),
            'pagination' => [
                'current_page' => $kegiatanData->currentPage(),
                'per_page' => $kegiatanData->perPage(),
                'total' => $kegiatanData->total(),
                'last_page' => $kegiatanData->lastPage()
            ],
            'filters_applied' => [
                'tanggal' => $tanggal,
                'unit_kerja' => $unitKerjaFilter,
                'search' => $search
            ]
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Generate attendance records untuk semua pegawai aktif pada tanggal tertentu
     */
    private function generateDailyAttendanceRecords($tanggal)
    {
        try {
            // Cek apakah sudah ada data untuk tanggal tersebut
            $existingCount = SimpegAbsensiRecord::whereDate('tanggal_absensi', $tanggal)->count();
            
            // Jika sudah ada data, skip generate
            if ($existingCount > 0) {
                return;
            }

            // Ambil semua pegawai aktif
            $pegawaiAktif = SimpegPegawai::where(function($query) {
                                            $query->where('status_kerja', 'Aktif')
                                                  ->orWhere('status_kerja', 'LIKE', '%aktif%')
                                                  ->orWhere('status_kerja', 'LIKE', '%AKTIF%');
                                        })
                                       ->orWhereHas('statusAktif', function($q) {
                                           $q->where('nama_status_aktif', 'like', '%aktif%')
                                             ->orWhere('nama_status_aktif', 'like', '%AKTIF%');
                                       })
                                       ->limit(1000)
                                       ->get();

            // Generate record default untuk setiap pegawai
            $records = [];
            foreach ($pegawaiAktif as $pegawai) {
                $defaultChecksum = md5($pegawai->id . '|' . $tanggal . '|default');
                
                $records[] = [
                    'pegawai_id' => $pegawai->id,
                    'tanggal_absensi' => $tanggal,
                    'status_verifikasi' => 'pending',
                    'check_sum_absensi' => $defaultChecksum,
                    'terlambat' => false,
                    'pulang_awal' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Batch insert
            if (!empty($records)) {
                $chunks = array_chunk($records, 100);
                foreach ($chunks as $chunk) {
                    SimpegAbsensiRecord::insert($chunk);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error generating daily attendance records: ' . $e->getMessage());
        }
    }

    /**
     * Get summary statistics untuk kegiatan
     */
    private function getSummaryStatistics($tanggal, $unitKerja = null)
    {
        $baseQuery = SimpegAbsensiRecord::whereDate('tanggal_absensi', $tanggal);
        
        if ($unitKerja) {
            $baseQuery->whereHas('pegawai', function($q) use ($unitKerja) {
                if (is_numeric($unitKerja)) {
                    $unitKerjaObj = SimpegUnitKerja::find($unitKerja);
                    if ($unitKerjaObj) {
                        $q->whereRaw("unit_kerja_id::text = ?", [$unitKerjaObj->kode_unit]);
                    }
                } else {
                    $q->whereRaw("unit_kerja_id::text = ?", [$unitKerja]);
                }
            });
        }

        $total = $baseQuery->count();
        $sudahAbsen = $baseQuery->clone()->whereNotNull('jam_masuk')->count();
        $sudahAbsenKeluar = $baseQuery->clone()->whereNotNull('jam_masuk')->whereNotNull('jam_keluar')->count();
        $adaRencana = $baseQuery->clone()->whereNotNull('rencana_kegiatan')->where('rencana_kegiatan', '!=', '')->count();
        $adaRealisasi = $baseQuery->clone()->whereNotNull('realisasi_kegiatan')->where('realisasi_kegiatan', '!=', '')->count();
        $lengkap = $baseQuery->clone()
                            ->whereNotNull('jam_masuk')
                            ->whereNotNull('jam_keluar')
                            ->whereNotNull('rencana_kegiatan')
                            ->whereNotNull('realisasi_kegiatan')
                            ->where('rencana_kegiatan', '!=', '')
                            ->where('realisasi_kegiatan', '!=', '')
                            ->count();

        return [
            'total_pegawai' => $total,
            'sudah_absen_masuk' => $sudahAbsen,
            'sudah_absen_keluar' => $sudahAbsenKeluar,
            'ada_rencana_kegiatan' => $adaRencana,
            'ada_realisasi_kegiatan' => $adaRealisasi,
            'data_lengkap' => $lengkap,
            'persentase_absen_masuk' => $total > 0 ? round(($sudahAbsen / $total) * 100, 2) : 0,
            'persentase_data_lengkap' => $total > 0 ? round(($lengkap / $total) * 100, 2) : 0
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
                                          })
        ];
    }

    /**
     * Format kegiatan data untuk tabel sesuai requirement
     */
    private function formatKegiatanData($kegiatan)
    {
        // Format jam masuk dengan lokasi
        $jamMasuk = '';
        if ($kegiatan->jam_masuk) {
            $jamMasuk = Carbon::parse($kegiatan->jam_masuk)->format('H:i:s');
            if ($kegiatan->lokasi_masuk) {
                $jamMasuk .= ' ' . $kegiatan->lokasi_masuk;
            }
        }

        // Format jam keluar dengan lokasi
        $jamKeluar = '';
        if ($kegiatan->jam_keluar) {
            $jamKeluar = Carbon::parse($kegiatan->jam_keluar)->format('H:i:s');
            if ($kegiatan->lokasi_keluar) {
                $jamKeluar .= ' ' . $kegiatan->lokasi_keluar;
            }
        }

        // Rencana dan realisasi pekerjaan
        $rencanaPekerjaan = $kegiatan->rencana_kegiatan ?? '-';
        $realisasiPekerjaan = $kegiatan->realisasi_kegiatan ?? '-';

        // Tentukan status berdasarkan kelengkapan data
        $status = 'Tidak Lengkap';
        $statusColor = 'danger';
        
        if ($kegiatan->jam_masuk && $kegiatan->jam_keluar && 
            $kegiatan->rencana_kegiatan && $kegiatan->realisasi_kegiatan) {
            $status = 'Lengkap';
            $statusColor = 'success';
        } elseif ($kegiatan->jam_masuk && $kegiatan->jam_keluar) {
            $status = 'Absen Lengkap';
            $statusColor = 'info';
        } elseif ($kegiatan->jam_masuk) {
            $status = 'Absen Masuk';
            $statusColor = 'warning';
        }

        // Validasi data
        $isValid = $this->validateKegiatanData($kegiatan);
        
        return [
            'nip' => $kegiatan->pegawai->nip,
            'nama' => $kegiatan->pegawai->nama,
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
            'rencana_pekerjaan' => $rencanaPekerjaan,
            'realisasi_pekerjaan' => $realisasiPekerjaan,
            'file' => $this->getFileInfo($kegiatan),
            'foto_masuk' => $kegiatan->foto_masuk ? url('storage/' . $kegiatan->foto_masuk) : null,
            'foto_keluar' => $kegiatan->foto_keluar ? url('storage/' . $kegiatan->foto_keluar) : null,
            'status' => $status,
            'status_color' => $statusColor,
            'valid' => $isValid,
            'detail' => [
                'id' => $kegiatan->id,
                'unit_kerja' => $kegiatan->pegawai->unitKerja->nama_unit ?? '-',
                'tanggal_absensi' => $kegiatan->tanggal_absensi->format('Y-m-d'),
                'koordinat_masuk' => [
                    'latitude' => $kegiatan->latitude_masuk,
                    'longitude' => $kegiatan->longitude_masuk
                ],
                'koordinat_keluar' => [
                    'latitude' => $kegiatan->latitude_keluar,
                    'longitude' => $kegiatan->longitude_keluar
                ],
                'durasi_kerja' => $kegiatan->getFormattedWorkingDuration(),
                'status_verifikasi' => $kegiatan->status_verifikasi,
                'keterangan' => $kegiatan->keterangan,
                'terlambat' => $kegiatan->terlambat,
                'pulang_awal' => $kegiatan->pulang_awal
            ],
            'actions' => $this->generateActionLinks($kegiatan)
        ];
    }

    /**
     * Validasi data kegiatan
     */
    private function validateKegiatanData($kegiatan)
    {
        $errors = [];
        
        // Validasi foto
        if ($kegiatan->jam_masuk && !$kegiatan->foto_masuk) {
            $errors[] = 'Foto masuk tidak ada';
        }
        
        if ($kegiatan->jam_keluar && !$kegiatan->foto_keluar) {
            $errors[] = 'Foto keluar tidak ada';
        }
        
        // Validasi koordinat
        if ($kegiatan->jam_masuk && (!$kegiatan->latitude_masuk || !$kegiatan->longitude_masuk)) {
            $errors[] = 'Koordinat masuk tidak lengkap';
        }
        
        if ($kegiatan->jam_keluar && (!$kegiatan->latitude_keluar || !$kegiatan->longitude_keluar)) {
            $errors[] = 'Koordinat keluar tidak lengkap';
        }
        
        // Validasi kegiatan
        if ($kegiatan->jam_masuk && (!$kegiatan->rencana_kegiatan || trim($kegiatan->rencana_kegiatan) === '')) {
            $errors[] = 'Rencana kegiatan belum diisi';
        }
        
        if ($kegiatan->jam_keluar && (!$kegiatan->realisasi_kegiatan || trim($kegiatan->realisasi_kegiatan) === '')) {
            $errors[] = 'Realisasi kegiatan belum diisi';
        }

        return empty($errors);
    }

    /**
     * Get file info jika ada
     */
    private function getFileInfo($kegiatan)
    {
        // Implementasi sesuai kebutuhan - bisa dari field tertentu atau dokumen terkait
        // Untuk sementara return placeholder
        return null;
    }

    /**
     * Generate action links
     */
    private function generateActionLinks($kegiatan)
    {
        $baseUrl = request()->getSchemeAndHttpHost();
        
        return [
            'detail' => [
                'url' => "{$baseUrl}/api/admin/monitoring-kegiatan/{$kegiatan->id}",
                'method' => 'GET',
                'label' => 'Lihat Detail'
            ],
            'foto_masuk' => [
                'url' => $kegiatan->foto_masuk ? url('storage/' . $kegiatan->foto_masuk) : null,
                'label' => 'Lihat Foto Masuk'
            ],
            'foto_keluar' => [
                'url' => $kegiatan->foto_keluar ? url('storage/' . $kegiatan->foto_keluar) : null,
                'label' => 'Lihat Foto Keluar'
            ]
        ];
    }

    /**
     * Show detail kegiatan
     */
    public function show($id)
    {
        $kegiatan = SimpegAbsensiRecord::with([
            'pegawai.unitKerja',
            'pegawai.jabatanAkademik',
            'jamKerja'
        ])->find($id);

        if (!$kegiatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data kegiatan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kegiatan_detail' => $this->formatDetailKegiatan($kegiatan),
                'pegawai_info' => $this->formatPegawaiInfo($kegiatan->pegawai),
                'validation_result' => [
                    'valid' => $this->validateKegiatanData($kegiatan),
                    'errors' => $this->getValidationErrors($kegiatan)
                ]
            ]
        ]);
    }

    /**
     * Format detail kegiatan
     */
    private function formatDetailKegiatan($kegiatan)
    {
        return [
            'basic_info' => [
                'id' => $kegiatan->id,
                'tanggal' => $kegiatan->tanggal_absensi->format('Y-m-d'),
                'hari' => $kegiatan->tanggal_absensi->locale('id')->isoFormat('dddd'),
                'jam_masuk' => $kegiatan->jam_masuk ? Carbon::parse($kegiatan->jam_masuk)->format('H:i:s') : null,
                'jam_keluar' => $kegiatan->jam_keluar ? Carbon::parse($kegiatan->jam_keluar)->format('H:i:s') : null,
                'durasi_kerja' => $kegiatan->getFormattedWorkingDuration()
            ],
            'kegiatan_info' => [
                'rencana_kegiatan' => $kegiatan->rencana_kegiatan,
                'realisasi_kegiatan' => $kegiatan->realisasi_kegiatan
            ],
            'location_info' => [
                'lokasi_masuk' => $kegiatan->lokasi_masuk,
                'lokasi_keluar' => $kegiatan->lokasi_keluar,
                'koordinat_masuk' => [
                    'latitude' => $kegiatan->latitude_masuk,
                    'longitude' => $kegiatan->longitude_masuk
                ],
                'koordinat_keluar' => [
                    'latitude' => $kegiatan->latitude_keluar,
                    'longitude' => $kegiatan->longitude_keluar
                ]
            ],
            'photos' => [
                'foto_masuk' => $kegiatan->foto_masuk ? url('storage/' . $kegiatan->foto_masuk) : null,
                'foto_keluar' => $kegiatan->foto_keluar ? url('storage/' . $kegiatan->foto_keluar) : null
            ],
            'status_info' => [
                'status_verifikasi' => $kegiatan->status_verifikasi,
                'terlambat' => $kegiatan->terlambat,
                'pulang_awal' => $kegiatan->pulang_awal,
                'keterangan' => $kegiatan->keterangan
            ]
        ];
    }

    /**
     * Format pegawai info
     */
    private function formatPegawaiInfo($pegawai)
    {
        return [
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_akademik' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'email' => $pegawai->email_pegawai ?? $pegawai->email_pribadi,
            'no_handphone' => $pegawai->no_handphone
        ];
    }

    /**
     * Get validation errors
     */
    private function getValidationErrors($kegiatan)
    {
        $errors = [];
        
        if ($kegiatan->jam_masuk && !$kegiatan->foto_masuk) {
            $errors[] = 'Foto masuk tidak ada';
        }
        
        if ($kegiatan->jam_keluar && !$kegiatan->foto_keluar) {
            $errors[] = 'Foto keluar tidak ada';
        }
        
        if ($kegiatan->jam_masuk && (!$kegiatan->latitude_masuk || !$kegiatan->longitude_masuk)) {
            $errors[] = 'Koordinat masuk tidak lengkap';
        }
        
        if ($kegiatan->jam_keluar && (!$kegiatan->latitude_keluar || !$kegiatan->longitude_keluar)) {
            $errors[] = 'Koordinat keluar tidak lengkap';
        }
        
        if ($kegiatan->jam_masuk && (!$kegiatan->rencana_kegiatan || trim($kegiatan->rencana_kegiatan) === '')) {
            $errors[] = 'Rencana kegiatan belum diisi';
        }
        
        if ($kegiatan->jam_keluar && (!$kegiatan->realisasi_kegiatan || trim($kegiatan->realisasi_kegiatan) === '')) {
            $errors[] = 'Realisasi kegiatan belum diisi';
        }

        return $errors;
    }
}