<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataJabatanAkademik;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegUnitKerja;
use Illuminate\Http\Request;
use Carbon\Carbon;

class KehadiranController extends Controller
{
public function index(Request $request)
{
    // Ambil pegawai_id dari parameter
    $pegawaiId = $request->input('pegawai_id');

    if (!$pegawaiId) {
        return response()->json([
            'success' => false,
            'message' => 'Parameter pegawai_id wajib diisi'
        ], 400);
    }

    $pegawai = SimpegPegawai::with([
        'statusAktif',
        'jabatanAkademik',
        'dataJabatanFungsional.jabatanFungsional',
        'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
        'dataPendidikanFormal.jenjangPendidikan'
    ])->find($pegawaiId);

    if (!$pegawai) {
        return response()->json([
            'success' => false,
            'message' => 'Pegawai tidak ditemukan'
        ], 404);
    }

    // Tahun default ke sekarang jika tidak diisi
    $tahun = $request->input('tahun', date('Y'));

    if (!is_numeric($tahun) || $tahun < 2020 || $tahun > date('Y') + 1) {
        return response()->json([
            'success' => false,
            'message' => 'Tahun tidak valid'
        ], 422);
    }

    $currentYear = date('Y');
    $currentMonth = date('n');
    $lastMonth = ($tahun == $currentYear) ? $currentMonth : 12;

    // Ambil data absensi berdasarkan pegawai dan tahun
    $attendanceData = $this->getMonthlyAttendanceData($pegawaiId, $tahun, $lastMonth);

    return response()->json([
        'success' => true,
        'data' => $attendanceData,
        'pegawai_info' => $this->formatPegawaiInfo($pegawai),
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
                        // Check absence type with status validation
                        $isValidAbsence = false;
                        
                        // Check cuti record with approved status
                        if ($record->cutiRecord && $record->cutiRecord->status_pengajuan === 'disetujui') {
                            $stats['cuti']++;
                            $isValidAbsence = true;
                        } 
                        // Check izin record with approved status
                        elseif ($record->izinRecord && $record->izinRecord->status_pengajuan === 'disetujui') {
                            // Check if it's sick leave or regular leave
                            $jenisIzin = $record->izinRecord->jenis_izin ?? '';
                            if (stripos($jenisIzin, 'sakit') !== false) {
                                $stats['sakit']++;
                            } else {
                                $stats['izin']++;
                            }
                            $isValidAbsence = true;
                        } 
                        // Check jenis kehadiran (fallback for older records)
                        elseif ($record->jenisKehadiran) {
                            $jenisKehadiran = strtolower($record->jenisKehadiran->nama_jenis ?? '');
                            if (stripos($jenisKehadiran, 'sakit') !== false) {
                                $stats['sakit']++;
                                $isValidAbsence = true;
                            } elseif (stripos($jenisKehadiran, 'izin') !== false) {
                                $stats['izin']++;
                                $isValidAbsence = true;
                            } elseif (stripos($jenisKehadiran, 'cuti') !== false) {
                                $stats['cuti']++;
                                $isValidAbsence = true;
                            }
                        }
                        
                        // If no valid approved absence, count as alpa
                        if (!$isValidAbsence) {
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
     private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) {
            return null;
        }

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
public function detail(Request $request)
{
    // Get parameters
    $pegawaiId = $request->input('pegawai_id');
    $tahun = $request->input('tahun', date('Y'));
    $bulan = $request->input('bulan', date('n'));

    if (!$pegawaiId) {
        return response()->json([
            'success' => false,
            'message' => 'Parameter pegawai_id wajib diisi'
        ], 400);
    }

    // Validate input
    if (!is_numeric($tahun) || $tahun < 2020 || $tahun > date('Y') + 1) {
        return response()->json([
            'success' => false,
            'message' => 'Tahun tidak valid'
        ], 422);
    }

    if (!is_numeric($bulan) || $bulan < 1 || $bulan > 12) {
        return response()->json([
            'success' => false,
            'message' => 'Bulan tidak valid'
        ], 422);
    }

    // Get pegawai info
    $pegawai = SimpegPegawai::with(['unitKerja', 'statusAktif'])->find($pegawaiId);

    if (!$pegawai) {
        return response()->json([
            'success' => false,
            'message' => 'Pegawai tidak ditemukan'
        ], 404);
    }

    // Get date range for the selected month
    $startDate = Carbon::create($tahun, $bulan, 1)->startOfMonth();
    $endDate = $startDate->copy()->endOfMonth();

    // Get daily attendance data
    $dailyData = $this->getEmployeeDailyAttendance($pegawai, $startDate, $endDate);

    return response()->json([
        'success' => true,
        'pegawai_info' => $this->formatPegawaiInfo($pegawai),
        'periode' => $this->getMonthName($bulan) . ' ' . $tahun,
        'daily_data' => $dailyData,
        'bulan_options' => $this->getMonthOptions(),
        'tahun_options' => $this->getYearOptions(),
        'table_columns' => [
            ['field' => 'tanggal', 'label' => 'Tanggal', 'sortable' => true],
            ['field' => 'hari', 'label' => 'Hari', 'sortable' => true],
            ['field' => 'status', 'label' => 'Status', 'sortable' => true],
            ['field' => 'jam_masuk', 'label' => 'Jam Masuk', 'sortable' => true],
            ['field' => 'jam_keluar', 'label' => 'Jam Keluar', 'sortable' => true],
            ['field' => 'terlambat', 'label' => 'Terlambat', 'sortable' => true],
            ['field' => 'pulang_awal', 'label' => 'Pulang Awal', 'sortable' => true],
            ['field' => 'lokasi', 'label' => 'Lokasi', 'sortable' => true],
            ['field' => 'keterangan', 'label' => 'Keterangan', 'sortable' => true]
        ]
    ]);
}

public function print(Request $request)
{
    // Get parameters
    $pegawaiId = $request->input('pegawai_id');
    $tahun = $request->input('tahun', date('Y'));
    $bulan = $request->input('bulan', date('n'));

    if (!$pegawaiId) {
        return response()->json([
            'success' => false,
            'message' => 'Parameter pegawai_id wajib diisi'
        ], 400);
    }

    // Validate input
    if (!is_numeric($tahun) || $tahun < 2020 || $tahun > date('Y') + 1) {
        return response()->json([
            'success' => false,
            'message' => 'Tahun tidak valid'
        ], 422);
    }

    if (!is_numeric($bulan) || $bulan < 1 || $bulan > 12) {
        return response()->json([
            'success' => false,
            'message' => 'Bulan tidak valid'
        ], 422);
    }

    // Get pegawai info
    $pegawai = SimpegPegawai::with(['unitKerja', 'statusAktif'])->find($pegawaiId);

    if (!$pegawai) {
        return response()->json([
            'success' => false,
            'message' => 'Pegawai tidak ditemukan'
        ], 404);
    }

    // Get date range for the selected month
    $startDate = Carbon::create($tahun, $bulan, 1)->startOfMonth();
    $endDate = $startDate->copy()->endOfMonth();

    // Get monthly attendance data
    $attendanceData = $this->getMonthlyAttendanceData($pegawaiId, $tahun, $bulan);
    $monthlyData = $attendanceData[0] ?? []; // Get first (and only) month

    // Get daily attendance data
    $dailyData = $this->getEmployeeDailyAttendance($pegawai, $startDate, $endDate);

    return response()->json([
        'success' => true,
        'pegawai_info' => $this->formatPegawaiInfo($pegawai),
        'periode' => $this->getMonthName($bulan) . ' ' . $tahun,
        'monthly_summary' => $monthlyData,
        'daily_data' => $dailyData,
        'print_date' => now()->format('d/m/Y H:i:s'),
        'table_columns' => [
            ['field' => 'tanggal', 'label' => 'Tanggal', 'sortable' => true],
            ['field' => 'hari', 'label' => 'Hari', 'sortable' => true],
            ['field' => 'status', 'label' => 'Status', 'sortable' => true],
            ['field' => 'jam_masuk', 'label' => 'Jam Masuk', 'sortable' => true],
            ['field' => 'jam_keluar', 'label' => 'Jam Keluar', 'sortable' => true],
            ['field' => 'terlambat', 'label' => 'Terlambat', 'sortable' => true],
            ['field' => 'pulang_awal', 'label' => 'Pulang Awal', 'sortable' => true],
            ['field' => 'lokasi', 'label' => 'Lokasi', 'sortable' => true],
            ['field' => 'keterangan', 'label' => 'Keterangan', 'sortable' => true]
        ]
    ]);
}

 

    private function getEmployeeAttendanceSummary($pegawai, $startDate, $endDate)
    {
        // Count working days (excluding Sundays)
        $hariKerja = 0;
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            if ($currentDate->dayOfWeek != Carbon::SUNDAY) {
                $hariKerja++;
            }
            $currentDate->addDay();
        }

        // Get attendance records for the period
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal_absensi', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord'])
            ->get();

        // Initialize counters
        $counters = [
            'hadir' => 0,
            'terlambat' => 0,
            'pulang_awal' => 0,
            'sakit' => 0,
            'izin' => 0,
            'cuti' => 0,
            'alpa' => 0
        ];

        // Check each day in the period
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek;

            if ($dayOfWeek != Carbon::SUNDAY) {
                $record = $attendanceRecords->firstWhere('tanggal_absensi', $dateKey);

                if ($record) {
                    if ($record->jam_masuk || $record->jam_keluar) {
                        $counters['hadir']++;
                        
                        if ($record->terlambat) {
                            $counters['terlambat']++;
                        }
                        if ($record->pulang_awal) {
                            $counters['pulang_awal']++;
                        }
                    } else {
                        // Check absence type with status validation
                        $isValidAbsence = false;
                        
                        // Check cuti record with approved status
                        if ($record->cutiRecord && $record->cutiRecord->status_pengajuan === 'disetujui') {
                            $counters['cuti']++;
                            $isValidAbsence = true;
                        } 
                        // Check izin record with approved status
                        elseif ($record->izinRecord && $record->izinRecord->status_pengajuan === 'disetujui') {
                            $jenisIzin = $record->izinRecord->jenis_izin ?? '';
                            if (stripos($jenisIzin, 'sakit') !== false) {
                                $counters['sakit']++;
                            } else {
                                $counters['izin']++;
                            }
                            $isValidAbsence = true;
                        } 
                        // Check jenis kehadiran (fallback for older records)
                        elseif ($record->jenisKehadiran) {
                            $jenisKehadiran = strtolower($record->jenisKehadiran->nama_jenis ?? '');
                            if (stripos($jenisKehadiran, 'sakit') !== false) {
                                $counters['sakit']++;
                                $isValidAbsence = true;
                            } elseif (stripos($jenisKehadiran, 'izin') !== false) {
                                $counters['izin']++;
                                $isValidAbsence = true;
                            } elseif (stripos($jenisKehadiran, 'cuti') !== false) {
                                $counters['cuti']++;
                                $isValidAbsence = true;
                            }
                        }
                        
                        // If no valid approved absence, count as alpa
                        if (!$isValidAbsence) {
                            $counters['alpa']++;
                        }
                    }
                } else {
                    // No record - Alpha
                    $counters['alpa']++;
                }
            }

            $currentDate->addDay();
        }

        // Calculate attendance percentage
        $persentase = $hariKerja > 0 ? round(($counters['hadir'] / $hariKerja) * 100, 2) : 0;

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'unit_kerja' => $pegawai->unitKerja ? $pegawai->unitKerja->nama_unit : '-',
            'hari_kerja' => $hariKerja,
            'hadir' => $counters['hadir'],
            'terlambat' => $counters['terlambat'],
            'pulang_awal' => $counters['pulang_awal'],
            'sakit' => $counters['sakit'],
            'izin' => $counters['izin'],
            'cuti' => $counters['cuti'],
            'alpa' => $counters['alpa'],
            'persentase' => $persentase,
            'detail_url' => route('admin.rekap-kehadiran.detail', [
                'pegawai' => $pegawai->id,
                'tahun' => $startDate->year,
                'bulan' => $startDate->month
            ])
        ];
    }

    private function getEmployeeDailyAttendance($pegawai, $startDate, $endDate)
    {
        // Get attendance records for the period
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal_absensi', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord', 'settingKehadiran'])
            ->get()
            ->keyBy(function($item) {
                return $item->tanggal_absensi->format('Y-m-d');
            });

        $dailyData = [];
        
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek;
            
            $hari = $currentDate->translatedFormat('l');
            $tanggal = $currentDate->format('d/m/Y');
            
            $record = $attendanceRecords[$dateKey] ?? null;

            $status = '-';
            $jamMasuk = '-';
            $jamKeluar = '-';
            $terlambat = '-';
            $pulangAwal = '-';
            $lokasi = '-';
            $keterangan = '-';

            if ($dayOfWeek == Carbon::SUNDAY) {
                $status = 'Libur';
                $keterangan = 'Hari Tidak Efektif';
            } elseif ($record) {
                if ($record->jam_masuk || $record->jam_keluar) {
                    $status = 'Hadir';
                    $jamMasuk = $record->jam_masuk ? $record->jam_masuk->format('H:i') : '-';
                    $jamKeluar = $record->jam_keluar ? $record->jam_keluar->format('H:i') : '-';
                    $terlambat = $record->terlambat ? $record->terlambat . ' menit' : '-';
                    $pulangAwal = $record->pulang_awal ? $record->pulang_awal . ' menit' : '-';
                    $lokasi = $record->settingKehadiran ? $record->settingKehadiran->nama_gedung : '-';
                    
                    $keterangan = '';
                    if ($record->terlambat) {
                        $keterangan .= 'Terlambat ';
                    }
                    if ($record->pulang_awal) {
                        $keterangan .= 'Pulang Awal ';
                    }
                    $keterangan = trim($keterangan) ?: 'Normal';
                } else {
                    // Check absence type with status validation
                    if ($record->cutiRecord && $record->cutiRecord->status_pengajuan === 'disetujui') {
                        $status = 'Cuti';
                        $keterangan = $record->cutiRecord->alasan_cuti ?? 'Cuti';
                    } elseif ($record->izinRecord && $record->izinRecord->status_pengajuan === 'disetujui') {
                        $jenisIzin = $record->izinRecord->jenis_izin ?? 'Izin';
                        if (stripos($jenisIzin, 'sakit') !== false) {
                            $status = 'Sakit';
                            $keterangan = $record->izinRecord->alasan ?? 'Sakit';
                        } else {
                            $status = 'Izin';
                            $keterangan = $record->izinRecord->alasan ?? 'Izin';
                        }
                    } elseif ($record->jenisKehadiran) {
                        $jenisKehadiran = $record->jenisKehadiran->nama_jenis ?? 'Alpha';
                        $status = $jenisKehadiran;
                        $keterangan = $jenisKehadiran;
                    } elseif ($record->cutiRecord || $record->izinRecord) {
                        // Has submission but not approved
                        $status = 'Alpha';
                        $submissionStatus = '';
                        if ($record->cutiRecord) {
                            $submissionStatus = $record->cutiRecord->status_pengajuan ?? 'belum diajukan';
                        } elseif ($record->izinRecord) {
                            $submissionStatus = $record->izinRecord->status_pengajuan ?? 'belum diajukan';
                        }
                        $keterangan = 'Pengajuan ' . ucfirst($submissionStatus);
                    } else {
                        $status = 'Alpha';
                        $keterangan = 'Tidak ada catatan kehadiran';
                    }
                }
            } else {
                $status = 'Alpha';
                $keterangan = 'Tidak ada catatan kehadiran';
            }

            $dailyData[] = [
                'tanggal' => $tanggal,
                'hari' => $hari,
                'status' => $status,
                'jam_masuk' => $jamMasuk,
                'jam_keluar' => $jamKeluar,
                'terlambat' => $terlambat,
                'pulang_awal' => $pulangAwal,
                'lokasi' => $lokasi,
                'keterangan' => $keterangan
            ];

            $currentDate->addDay();
        }

        return $dailyData;
    }

    private function getMonthOptions()
    {
        return [
            ['value' => 1, 'label' => 'Januari'],
            ['value' => 2, 'label' => 'Februari'],
            ['value' => 3, 'label' => 'Maret'],
            ['value' => 4, 'label' => 'April'],
            ['value' => 5, 'label' => 'Mei'],
            ['value' => 6, 'label' => 'Juni'],
            ['value' => 7, 'label' => 'Juli'],
            ['value' => 8, 'label' => 'Agustus'],
            ['value' => 9, 'label' => 'September'],
            ['value' => 10, 'label' => 'Oktober'],
            ['value' => 11, 'label' => 'November'],
            ['value' => 12, 'label' => 'Desember']
        ];
    }
private function calculateYearlySummary($rekapData)
{
    $summary = [
        'hari_kerja' => 0,
        'hadir' => 0,
        'terlambat' => 0,
        'pulang_awal' => 0,
        'sakit' => 0,
        'izin' => 0,
        'cuti' => 0,
        'alpa' => 0,
    ];

    foreach ($rekapData as $data) {
        foreach ($summary as $key => $val) {
            $summary[$key] += $data[$key];
        }
    }

    return $summary;
}

private function getYearOptions()
{
    $currentYear = date('Y');
    $years = [];
    
    // Get years from attendance records - PostgreSQL compatible version
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

    private function getMonthName($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$month] ?? '';
    }
}