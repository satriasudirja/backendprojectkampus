<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenisKehadiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RiwayatKehadiranController extends Controller
{
    /**
     * Menampilkan riwayat kehadiran per bulan untuk pegawai yang login
     */
    public function index(Request $request)
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        
        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Get year parameter, default to current year
        $tahun = $request->input('tahun', date('Y'));
        
        // Validate year
        if (!is_numeric($tahun) || $tahun < 2020 || $tahun > date('Y') + 1) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun tidak valid'
            ], 422);
        }

        // Determine last month to show
        $currentYear = date('Y');
        $currentMonth = date('n');
        $lastMonth = ($tahun == $currentYear) ? $currentMonth : 12;

        // Get attendance data for the year
        $attendanceData = $this->getMonthlyAttendanceData($pegawai->id, $tahun, $lastMonth);
        
        // Get pegawai info
        $pegawaiInfo = $this->formatPegawaiInfo($pegawai->load([
            'unitKerja', 'statusAktif', 'jabatanAkademik'
        ]));

        return response()->json([
            'success' => true,
            'data' => $attendanceData,
            'pegawai_info' => $pegawaiInfo,
            'tahun' => $tahun,
            'tahun_options' => $this->getYearOptions(),
            'summary' => $this->calculateYearlySummary($attendanceData),
            'table_columns' => [
                ['field' => 'bulan', 'label' => 'Bulan', 'sortable' => false],
                ['field' => 'hari_kerja', 'label' => 'Hari Kerja', 'sortable' => false],
                ['field' => 'hadir', 'label' => 'Hadir', 'sortable' => false],
                ['field' => 'hadir_libur', 'label' => 'Hadir Libur', 'sortable' => false],
                ['field' => 'terlambat', 'label' => 'Terlambat', 'sortable' => false],
                ['field' => 'pulang_awal', 'label' => 'Pulang Awal', 'sortable' => false],
                ['field' => 'sakit', 'label' => 'Sakit', 'sortable' => false],
                ['field' => 'izin', 'label' => 'Izin', 'sortable' => false],
                ['field' => 'alpa', 'label' => 'Alpa', 'sortable' => false],
                ['field' => 'cuti', 'label' => 'Cuti', 'sortable' => false],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ]
        ]);
    }

    /**
     * Detail presensi harian untuk bulan tertentu
     */
    public function detail(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        $tahun = $request->input('tahun', date('Y'));
        $bulan = $request->input('bulan', date('n'));

        // Validate input
        if (!is_numeric($tahun) || !is_numeric($bulan) || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tahun atau bulan tidak valid'
            ], 422);
        }

        // Get daily attendance data for the month
        $dailyData = $this->getDailyAttendanceData($pegawai->id, $tahun, $bulan);
        
        // Format period
        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $periode = $startDate->format('d') . ' s.d. ' . $endDate->format('d') . ' ' . 
                  $this->getMonthName($bulan) . ' ' . $tahun;

        return response()->json([
            'success' => true,
            'data' => $dailyData,
            'periode' => $periode,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'bulan_nama' => $this->getMonthName($bulan),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanAkademik'
            ])),
            'table_columns' => [
                ['field' => 'no', 'label' => 'No.', 'sortable' => false],
                ['field' => 'hari_tanggal', 'label' => 'Hari dan Tanggal', 'sortable' => false],
                ['field' => 'datang', 'label' => 'Datang', 'sortable' => false],
                ['field' => 'pulang', 'label' => 'Pulang', 'sortable' => false],
                ['field' => 'lokasi_datang', 'label' => 'Lokasi Datang', 'sortable' => false],
                ['field' => 'lokasi_pulang', 'label' => 'Lokasi Pulang', 'sortable' => false],
                ['field' => 'jenis_presensi', 'label' => 'Jenis Presensi', 'sortable' => false],
                ['field' => 'keterangan', 'label' => 'Keterangan', 'sortable' => false]
            ]
        ]);
    }

    /**
     * Print/cetak daftar riwayat kehadiran semua pegawai
     */
    public function print(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $tahun = $request->input('tahun', date('Y'));
        $bulan = $request->input('bulan', date('n'));
        $tanggal_mulai = $request->input('tanggal_mulai');
        $tanggal_selesai = $request->input('tanggal_selesai');

        // Set default period if specific dates not provided
        if (!$tanggal_mulai || !$tanggal_selesai) {
            $startDate = Carbon::create($tahun, $bulan, 1);
            $endDate = $startDate->copy()->endOfMonth();
            $tanggal_mulai = $startDate->format('Y-m-d');
            $tanggal_selesai = $endDate->format('Y-m-d');
        }

        // Get all employees attendance data for the period
        $allEmployeesData = $this->getAllEmployeesAttendanceData($tanggal_mulai, $tanggal_selesai);
        
        // Format period for display
        $startDate = Carbon::parse($tanggal_mulai);
        $endDate = Carbon::parse($tanggal_selesai);
        
        $periode = $startDate->translatedFormat('l, d F Y') . ' s.d. ' . 
                  $endDate->translatedFormat('l, d F Y');

        return response()->json([
            'success' => true,
            'data' => $allEmployeesData,
            'periode' => $periode,
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_selesai' => $tanggal_selesai,
            'total_pegawai' => count($allEmployeesData),
            'print_info' => [
                'title' => 'Daftar Riwayat Kehadiran',
                'subtitle' => 'Daftar Riwayat Kehadiran',
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'generated_by' => Auth::user()->nama
            ],
            'table_columns' => [
                ['field' => 'no', 'label' => 'No', 'sortable' => false],
                ['field' => 'nip', 'label' => 'NIP', 'sortable' => false],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => false],
                ['field' => 'jam_masuk', 'label' => 'Jam Masuk', 'sortable' => false],
                ['field' => 'jam_realisasi_masuk', 'label' => 'Jam Realisasi', 'sortable' => false],
                ['field' => 'keterlambatan', 'label' => 'Keterlambatan', 'sortable' => false],
                ['field' => 'jam_keluar', 'label' => 'Jam Keluar', 'sortable' => false],
                ['field' => 'jam_realisasi_keluar', 'label' => 'Jam Realisasi', 'sortable' => false],
                ['field' => 'jam_kerja_kurang', 'label' => 'Jam Kerja Kurang', 'sortable' => false],
                ['field' => 'jam_kerja_lebih', 'label' => 'Jam Kerja Lebih', 'sortable' => false],
                ['field' => 'realisasi_jam_kerja', 'label' => 'Realisasi Jam Kerja', 'sortable' => false],
                ['field' => 'total_jam_kerja', 'label' => 'Total Jam Kerja', 'sortable' => false],
                ['field' => 'keterangan', 'label' => 'Keterangan', 'sortable' => false],
                ['field' => 'lokasi_masuk', 'label' => 'Lokasi Masuk', 'sortable' => false],
                ['field' => 'lokasi_keluar', 'label' => 'Lokasi Keluar', 'sortable' => false],
                ['field' => 'rencana_pekerjaan', 'label' => 'Rencana Pekerjaan', 'sortable' => false],
                ['field' => 'realisasi_pekerjaan', 'label' => 'Realisasi Pekerjaan', 'sortable' => false]
            ]
        ]);
    }

    /**
     * Get monthly attendance data for a specific employee
     */
    private function getMonthlyAttendanceData($pegawaiId, $tahun, $lastMonth)
    {
        $data = [];
        
        for ($bulan = 1; $bulan <= $lastMonth; $bulan++) {
            $startDate = Carbon::create($tahun, $bulan, 1);
            $endDate = $startDate->copy()->endOfMonth();
            
            // Count working days (Monday to Saturday, excluding Sunday)
            $hariKerja = 0;
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                if ($currentDate->dayOfWeek != Carbon::SUNDAY) {
                    $hariKerja++;
                }
                $currentDate->addDay();
            }

            // Get attendance statistics for this month
            $attendanceStats = $this->getMonthlyAttendanceStats($pegawaiId, $tahun, $bulan);

            $data[] = [
                'bulan' => $this->getMonthName($bulan),
                'bulan_number' => $bulan,
                'hari_kerja' => $hariKerja,
                'hadir' => $attendanceStats['hadir'],
                'hadir_libur' => $attendanceStats['hadir_libur'],
                'terlambat' => $attendanceStats['terlambat'],
                'pulang_awal' => $attendanceStats['pulang_awal'],
                'sakit' => $attendanceStats['sakit'],
                'izin' => $attendanceStats['izin'],
                'alpa' => $attendanceStats['alpa'],
                'cuti' => $attendanceStats['cuti'],
                'aksi' => [
                    'detail_url' => url("/api/riwayat-kehadiran/detail?tahun={$tahun}&bulan={$bulan}"),
                    'print_url' => url("/api/riwayat-kehadiran/print?tahun={$tahun}&bulan={$bulan}")
                ]
            ];
        }

        return $data;
    }

    /**
     * Get attendance statistics for a specific month
     */
    private function getMonthlyAttendanceStats($pegawaiId, $tahun, $bulan)
    {
        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();

        // Initialize counters
        $stats = [
            'hadir' => 0,
            'hadir_libur' => 0,
            'terlambat' => 0,
            'pulang_awal' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
            'cuti' => 0
        ];

        // Count working days in month
        $workingDaysInMonth = 0;
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            if ($currentDate->dayOfWeek != Carbon::SUNDAY) {
                $workingDaysInMonth++;
            }
            $currentDate->addDay();
        }

        // Get attendance records for the month
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord'])
            ->get()
            ->keyBy(function($item) {
                return $item->tanggal_absensi->format('Y-m-d');
            });

        // Check each day in the month
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek;
            
            if ($dayOfWeek == Carbon::SUNDAY) {
                // Sunday - check if there's attendance (hadir libur)
                if (isset($attendanceRecords[$dateKey])) {
                    $record = $attendanceRecords[$dateKey];
                    if ($record->jam_masuk || $record->jam_keluar) {
                        $stats['hadir_libur']++;
                    }
                }
            } else {
                // Working day
                if (isset($attendanceRecords[$dateKey])) {
                    $record = $attendanceRecords[$dateKey];
                    
                    // Check if present
                    if ($record->jam_masuk || $record->jam_keluar) {
                        $stats['hadir']++;
                        
                        // Check for late arrival
                        if ($record->terlambat) {
                            $stats['terlambat']++;
                        }
                        
                        // Check for early departure
                        if ($record->pulang_awal) {
                            $stats['pulang_awal']++;
                        }
                    } else {
                        // Check absence type
                        if ($record->cutiRecord) {
                            $stats['cuti']++;
                        } elseif ($record->izinRecord) {
                            // Check if it's sick leave or regular leave
                            $jenisIzin = $record->izinRecord->jenis_izin ?? '';
                            if (stripos($jenisIzin, 'sakit') !== false) {
                                $stats['sakit']++;
                            } else {
                                $stats['izin']++;
                            }
                        } elseif ($record->jenisKehadiran) {
                            $jenisKehadiran = strtolower($record->jenisKehadiran->nama_jenis ?? '');
                            if (stripos($jenisKehadiran, 'sakit') !== false) {
                                $stats['sakit']++;
                            } elseif (stripos($jenisKehadiran, 'izin') !== false) {
                                $stats['izin']++;
                            } elseif (stripos($jenisKehadiran, 'cuti') !== false) {
                                $stats['cuti']++;
                            } else {
                                $stats['alpa']++;
                            }
                        } else {
                            $stats['alpa']++;
                        }
                    }
                } else {
                    // No record - Alpha
                    $stats['alpa']++;
                }
            }
            
            $currentDate->addDay();
        }

        return $stats;
    }

    /**
     * Get daily attendance data for a specific month
     */
    private function getDailyAttendanceData($pegawaiId, $tahun, $bulan)
    {
        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        // Get attendance records for the month
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord', 'settingKehadiran'])
            ->get()
            ->keyBy(function($item) {
                return $item->tanggal_absensi->format('Y-m-d');
            });

        $data = [];
        $no = 1;
        
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek;
            
            $hariTanggal = $currentDate->translatedFormat('l, j M Y');
            
            if ($dayOfWeek == Carbon::SUNDAY) {
                // Sunday
                $data[] = [
                    'no' => $no,
                    'hari_tanggal' => $hariTanggal,
                    'datang' => '',
                    'pulang' => '',
                    'lokasi_datang' => '',
                    'lokasi_pulang' => '',
                    'jenis_presensi' => '',
                    'keterangan' => 'Hari Tidak Efektif'
                ];
            } else {
                // Working day
                if (isset($attendanceRecords[$dateKey])) {
                    $record = $attendanceRecords[$dateKey];
                    
                    $datang = $record->jam_masuk ? $record->jam_masuk->format('H:i') : '';
                    $pulang = $record->jam_keluar ? $record->jam_keluar->format('H:i') : '';
                    
                    $lokasi = $record->settingKehadiran ? $record->settingKehadiran->nama_gedung : '';
                    
                    if ($datang || $pulang) {
                        $jenisPresensi = 'Hadir';
                        $keterangan = '';
                        
                        if ($record->terlambat) {
                            $keterangan .= 'Terlambat ';
                        }
                        if ($record->pulang_awal) {
                            $keterangan .= 'Pulang Awal ';
                        }
                        
                        $keterangan = trim($keterangan) ?: 'Normal';
                    } else {
                        // Check absence type
                        if ($record->cutiRecord) {
                            $jenisPresensi = 'Cuti';
                            $keterangan = $record->cutiRecord->alasan_cuti ?? 'Cuti';
                        } elseif ($record->izinRecord) {
                            $jenisIzin = $record->izinRecord->jenis_izin ?? 'Izin';
                            if (stripos($jenisIzin, 'sakit') !== false) {
                                $jenisPresensi = 'Sakit';
                                $keterangan = $record->izinRecord->alasan ?? 'Sakit';
                            } else {
                                $jenisPresensi = 'Izin';
                                $keterangan = $record->izinRecord->alasan ?? 'Izin';
                            }
                        } elseif ($record->jenisKehadiran) {
                            $jenisKehadiranNama = $record->jenisKehadiran->nama_jenis ?? 'Alpha';
                            if (stripos($jenisKehadiranNama, 'sakit') !== false) {
                                $jenisPresensi = 'Sakit';
                                $keterangan = 'Sakit';
                            } elseif (stripos($jenisKehadiranNama, 'izin') !== false) {
                                $jenisPresensi = 'Izin';
                                $keterangan = 'Izin';
                            } elseif (stripos($jenisKehadiranNama, 'cuti') !== false) {
                                $jenisPresensi = 'Cuti';
                                $keterangan = 'Cuti';
                            } else {
                                $jenisPresensi = 'Alpha';
                                $keterangan = 'Belum melakukan Presensi';
                            }
                        } else {
                            $jenisPresensi = 'Alpha';
                            $keterangan = 'Belum melakukan Presensi';
                        }
                    }
                    
                    $data[] = [
                        'no' => $no,
                        'hari_tanggal' => $hariTanggal,
                        'datang' => $datang,
                        'pulang' => $pulang,
                        'lokasi_datang' => $lokasi,
                        'lokasi_pulang' => $lokasi,
                        'jenis_presensi' => $jenisPresensi,
                        'keterangan' => $keterangan
                    ];
                } else {
                    // No record - Alpha
                    $data[] = [
                        'no' => $no,
                        'hari_tanggal' => $hariTanggal,
                        'datang' => '',
                        'pulang' => '',
                        'lokasi_datang' => '',
                        'lokasi_pulang' => '',
                        'jenis_presensi' => 'Alpha',
                        'keterangan' => 'Belum melakukan Presensi'
                    ];
                }
            }
            
            $no++;
            $currentDate->addDay();
        }

        return $data;
    }

    /**
     * Get attendance data for all employees in a period
     */
    private function getAllEmployeesAttendanceData($tanggalMulai, $tanggalSelesai)
    {
        $startDate = Carbon::parse($tanggalMulai);
        $endDate = Carbon::parse($tanggalSelesai);
        
        // Get all active employees
        $employees = SimpegPegawai::with(['statusAktif', 'unitKerja'])
            ->whereHas('statusAktif', function($query) {
                $query->where('nama_status_aktif', 'Aktif');
            })
            ->orderBy('nip')
            ->get();

        $data = [];
        
        // Process each date in the period
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayName = $currentDate->translatedFormat('l, j F Y');
            
            // Add date header
            $data[] = [
                'type' => 'date_header',
                'date' => $dateKey,
                'date_formatted' => $dayName,
                'employees' => []
            ];
            
            $no = 1;
            foreach ($employees as $employee) {
                // Get attendance record for this employee on this date
                $attendanceRecord = SimpegAbsensiRecord::where('pegawai_id', $employee->id)
                    ->where('tanggal_absensi', $dateKey)
                    ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord', 'settingKehadiran'])
                    ->first();

                $employeeData = [
                    'no' => $no,
                    'nip' => "'" . $employee->nip,
                    'nama_pegawai' => $employee->nama,
                    'jam_masuk' => ':',
                    'jam_realisasi_masuk' => '',
                    'keterlambatan' => '-',
                    'jam_keluar' => ':',
                    'jam_realisasi_keluar' => '',
                    'jam_kerja_kurang' => '-',
                    'jam_kerja_lebih' => '-',
                    'realisasi_jam_kerja' => '-',
                    'total_jam_kerja' => '-',
                    'keterangan' => '-',
                    'lokasi_masuk' => '-',
                    'lokasi_keluar' => '-',
                    'rencana_pekerjaan' => '-',
                    'realisasi_pekerjaan' => '-'
                ];

                if ($attendanceRecord) {
                    if ($attendanceRecord->jam_masuk) {
                        $employeeData['jam_realisasi_masuk'] = $attendanceRecord->jam_masuk->format('H:i');
                    }
                    if ($attendanceRecord->jam_keluar) {
                        $employeeData['jam_realisasi_keluar'] = $attendanceRecord->jam_keluar->format('H:i');
                    }
                    
                    if ($attendanceRecord->settingKehadiran) {
                        $employeeData['lokasi_masuk'] = $attendanceRecord->settingKehadiran->nama_gedung;
                        $employeeData['lokasi_keluar'] = $attendanceRecord->settingKehadiran->nama_gedung;
                    }
                }

                $data[count($data) - 1]['employees'][] = $employeeData;
                $no++;
            }
            
            $currentDate->addDay();
        }

        return $data;
    }

    /**
     * Calculate yearly summary
     */
    private function calculateYearlySummary($attendanceData)
    {
        $summary = [
            'total_hari_kerja' => 0,
            'total_hadir' => 0,
            'total_hadir_libur' => 0,
            'total_terlambat' => 0,
            'total_pulang_awal' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
            'total_cuti' => 0
        ];

        foreach ($attendanceData as $month) {
            $summary['total_hari_kerja'] += $month['hari_kerja'];
            $summary['total_hadir'] += $month['hadir'];
            $summary['total_hadir_libur'] += $month['hadir_libur'];
            $summary['total_terlambat'] += $month['terlambat'];
            $summary['total_pulang_awal'] += $month['pulang_awal'];
            $summary['total_sakit'] += $month['sakit'];
            $summary['total_izin'] += $month['izin'];
            $summary['total_alpa'] += $month['alpa'];
            $summary['total_cuti'] += $month['cuti'];
        }

        // Calculate percentages
        if ($summary['total_hari_kerja'] > 0) {
            $summary['persentase_kehadiran'] = round(($summary['total_hadir'] / $summary['total_hari_kerja']) * 100, 2);
            $summary['persentase_ketidakhadiran'] = round((($summary['total_sakit'] + $summary['total_izin'] + $summary['total_alpa'] + $summary['total_cuti']) / $summary['total_hari_kerja']) * 100, 2);
        } else {
            $summary['persentase_kehadiran'] = 0;
            $summary['persentase_ketidakhadiran'] = 0;
        }

        return $summary;
    }

    /**
     * Get year options for filter
     */
    private function getYearOptions()
    {
        $currentYear = date('Y');
        $years = [];
        
        // Get years from attendance records - PostgreSQL compatible
        $existingYears = SimpegAbsensiRecord::selectRaw('EXTRACT(YEAR FROM tanggal_absensi) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Merge with current year and next year
        $allYears = array_unique(array_merge($existingYears, [$currentYear, $currentYear + 1]));
        rsort($allYears);

        foreach ($allYears as $year) {
            $years[] = [
                'value' => $year,
                'label' => $year,
                'is_current' => $year == $currentYear
            ];
        }

        return $years;
    }

    /**
     * Get month name in Indonesian
     */
    private function getMonthName($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$month] ?? '';
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
            'unit_kerja' => $pegawai->unitKerja ? $pegawai->unitKerja->nama_unit : '-',
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jabatan_akademik' => $pegawai->jabatanAkademik ? $pegawai->jabatanAkademik->jabatan_akademik : '-'
        ];
    }
}