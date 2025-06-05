<?php

namespace App\Services;

use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegSettingKehadiran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCalculationService
{
    /**
     * Calculate working hours for attendance record
     */
    public static function calculateWorkingHours($attendanceRecord)
    {
        if (!$attendanceRecord->jam_masuk || !$attendanceRecord->jam_keluar) {
            return [
                'jam_kerja_realisasi' => '00:00',
                'jam_kerja_total' => '00:00',
                'jam_kerja_kurang' => '00:00',
                'jam_kerja_lebih' => '00:00',
                'keterlambatan' => '00:00'
            ];
        }

        $jamMasuk = Carbon::parse($attendanceRecord->jam_masuk);
        $jamKeluar = Carbon::parse($attendanceRecord->jam_keluar);
        
        // Get setting kehadiran for standard working hours
        $setting = $attendanceRecord->settingKehadiran;
        
        // Default working hours (8 hours)
        $jamKerjaStandar = 8; // hours
        $jamMasukStandar = Carbon::createFromTime(8, 0, 0); // 08:00
        $jamKeluarStandar = Carbon::createFromTime(16, 0, 0); // 16:00
        
        // Calculate actual working hours
        $jamKerjaRealisasi = $jamKeluar->diffInMinutes($jamMasuk) / 60;
        
        // Calculate lateness
        $keterlambatan = 0;
        if ($jamMasuk->gt($jamMasukStandar)) {
            $keterlambatan = $jamMasuk->diffInMinutes($jamMasukStandar);
        }
        
        // Calculate early departure
        $pulangAwal = 0;
        if ($jamKeluar->lt($jamKeluarStandar)) {
            $pulangAwal = $jamKeluarStandar->diffInMinutes($jamKeluar);
        }
        
        // Calculate work shortage/excess
        $jamKerjaKurang = 0;
        $jamKerjaLebih = 0;
        
        if ($jamKerjaRealisasi < $jamKerjaStandar) {
            $jamKerjaKurang = ($jamKerjaStandar - $jamKerjaRealisasi) * 60; // in minutes
        } else {
            $jamKerjaLebih = ($jamKerjaRealisasi - $jamKerjaStandar) * 60; // in minutes
        }

        return [
            'jam_kerja_realisasi' => self::formatMinutesToTime($jamKerjaRealisasi * 60),
            'jam_kerja_total' => self::formatMinutesToTime($jamKerjaStandar * 60),
            'jam_kerja_kurang' => self::formatMinutesToTime($jamKerjaKurang),
            'jam_kerja_lebih' => self::formatMinutesToTime($jamKerjaLebih),
            'keterlambatan' => self::formatMinutesToTime($keterlambatan)
        ];
    }

    /**
     * Calculate monthly attendance statistics
     */
    public static function calculateMonthlyStats($pegawaiId, $year, $month)
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        $stats = [
            'total_hari_kerja' => 0,
            'total_hadir' => 0,
            'total_hadir_libur' => 0,
            'total_terlambat' => 0,
            'total_pulang_awal' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
            'total_cuti' => 0,
            'total_jam_kerja_realisasi' => 0,
            'total_jam_kerja_standar' => 0,
            'total_keterlambatan' => 0
        ];

        // Count working days
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            if ($currentDate->dayOfWeek != Carbon::SUNDAY) {
                $stats['total_hari_kerja']++;
            }
            $currentDate->addDay();
        }

        // Get attendance records
        $records = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$startDate, $endDate])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord'])
            ->get();

        foreach ($records as $record) {
            $dayOfWeek = $record->tanggal_absensi->dayOfWeek;
            
            if ($dayOfWeek == Carbon::SUNDAY) {
                // Sunday attendance
                if ($record->jam_masuk || $record->jam_keluar) {
                    $stats['total_hadir_libur']++;
                }
            } else {
                // Working day
                if ($record->jam_masuk || $record->jam_keluar) {
                    $stats['total_hadir']++;
                    
                    if ($record->terlambat) {
                        $stats['total_terlambat']++;
                    }
                    
                    if ($record->pulang_awal) {
                        $stats['total_pulang_awal']++;
                    }
                    
                    // Calculate working hours
                    $workingHours = self::calculateWorkingHours($record);
                    $stats['total_jam_kerja_realisasi'] += self::timeToMinutes($workingHours['jam_kerja_realisasi']);
                    $stats['total_keterlambatan'] += self::timeToMinutes($workingHours['keterlambatan']);
                } else {
                    // Absent
                    if ($record->cutiRecord) {
                        $stats['total_cuti']++;
                    } elseif ($record->izinRecord) {
                        $jenisIzin = strtolower($record->izinRecord->jenis_izin ?? '');
                        if (strpos($jenisIzin, 'sakit') !== false) {
                            $stats['total_sakit']++;
                        } else {
                            $stats['total_izin']++;
                        }
                    } elseif ($record->jenisKehadiran) {
                        $jenisKehadiran = strtolower($record->jenisKehadiran->nama_jenis ?? '');
                        if (strpos($jenisKehadiran, 'sakit') !== false) {
                            $stats['total_sakit']++;
                        } elseif (strpos($jenisKehadiran, 'izin') !== false) {
                            $stats['total_izin']++;
                        } elseif (strpos($jenisKehadiran, 'cuti') !== false) {
                            $stats['total_cuti']++;
                        } else {
                            $stats['total_alpa']++;
                        }
                    } else {
                        $stats['total_alpa']++;
                    }
                }
            }
        }

        // Calculate standard working hours
        $stats['total_jam_kerja_standar'] = $stats['total_hari_kerja'] * 8 * 60; // 8 hours in minutes

        // Calculate percentages
        if ($stats['total_hari_kerja'] > 0) {
            $stats['persentase_kehadiran'] = round(($stats['total_hadir'] / $stats['total_hari_kerja']) * 100, 2);
        } else {
            $stats['persentase_kehadiran'] = 0;
        }

        return $stats;
    }

    /**
     * Get attendance status for a specific date
     */
    public static function getAttendanceStatus($attendanceRecord, $date)
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        
        if ($dayOfWeek == Carbon::SUNDAY) {
            if ($attendanceRecord && ($attendanceRecord->jam_masuk || $attendanceRecord->jam_keluar)) {
                return [
                    'status' => 'hadir_libur',
                    'label' => 'Hadir Libur',
                    'color' => 'info',
                    'description' => 'Hadir pada hari libur'
                ];
            } else {
                return [
                    'status' => 'libur',
                    'label' => 'Hari Libur',
                    'color' => 'secondary',
                    'description' => 'Hari tidak efektif'
                ];
            }
        }

        if (!$attendanceRecord) {
            return [
                'status' => 'alpa',
                'label' => 'Alpha',
                'color' => 'danger',
                'description' => 'Tidak ada presensi'
            ];
        }

        if ($attendanceRecord->jam_masuk || $attendanceRecord->jam_keluar) {
            $status = 'hadir';
            $description = 'Hadir';
            $color = 'success';
            
            if ($attendanceRecord->terlambat && $attendanceRecord->pulang_awal) {
                $description = 'Hadir (Terlambat & Pulang Awal)';
                $color = 'warning';
            } elseif ($attendanceRecord->terlambat) {
                $description = 'Hadir (Terlambat)';
                $color = 'warning';
            } elseif ($attendanceRecord->pulang_awal) {
                $description = 'Hadir (Pulang Awal)';
                $color = 'warning';
            }
            
            return [
                'status' => $status,
                'label' => 'Hadir',
                'color' => $color,
                'description' => $description
            ];
        }

        // Check absence type
        if ($attendanceRecord->cutiRecord) {
            return [
                'status' => 'cuti',
                'label' => 'Cuti',
                'color' => 'primary',
                'description' => $attendanceRecord->cutiRecord->alasan_cuti ?? 'Cuti'
            ];
        }

        if ($attendanceRecord->izinRecord) {
            $jenisIzin = strtolower($attendanceRecord->izinRecord->jenis_izin ?? '');
            if (strpos($jenisIzin, 'sakit') !== false) {
                return [
                    'status' => 'sakit',
                    'label' => 'Sakit',
                    'color' => 'warning',
                    'description' => $attendanceRecord->izinRecord->alasan ?? 'Sakit'
                ];
            } else {
                return [
                    'status' => 'izin',
                    'label' => 'Izin',
                    'color' => 'info',
                    'description' => $attendanceRecord->izinRecord->alasan ?? 'Izin'
                ];
            }
        }

        if ($attendanceRecord->jenisKehadiran) {
            $jenisKehadiran = strtolower($attendanceRecord->jenisKehadiran->nama_jenis ?? '');
            if (strpos($jenisKehadiran, 'sakit') !== false) {
                return [
                    'status' => 'sakit',
                    'label' => 'Sakit',
                    'color' => 'warning',
                    'description' => 'Sakit'
                ];
            } elseif (strpos($jenisKehadiran, 'izin') !== false) {
                return [
                    'status' => 'izin',
                    'label' => 'Izin',
                    'color' => 'info',
                    'description' => 'Izin'
                ];
            } elseif (strpos($jenisKehadiran, 'cuti') !== false) {
                return [
                    'status' => 'cuti',
                    'label' => 'Cuti',
                    'color' => 'primary',
                    'description' => 'Cuti'
                ];
            }
        }

        return [
            'status' => 'alpa',
            'label' => 'Alpha',
            'color' => 'danger',
            'description' => 'Belum melakukan presensi'
        ];
    }

    /**
     * Generate attendance report data
     */
    public static function generateAttendanceReport($pegawaiId, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $records = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$start, $end])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord', 'settingKehadiran'])
            ->orderBy('tanggal_absensi')
            ->get()
            ->keyBy(function($item) {
                return $item->tanggal_absensi->format('Y-m-d');
            });

        $reportData = [];
        $summary = [
            'total_hari' => 0,
            'total_hari_kerja' => 0,
            'total_hadir' => 0,
            'total_terlambat' => 0,
            'total_pulang_awal' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
            'total_cuti' => 0,
            'total_libur' => 0
        ];

        $currentDate = $start->copy();
        while ($currentDate <= $end) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek;
            
            $record = $records->get($dateKey);
            $attendanceStatus = self::getAttendanceStatus($record, $currentDate);
            
            $dayData = [
                'tanggal' => $currentDate->format('Y-m-d'),
                'hari_tanggal' => $currentDate->translatedFormat('l, j F Y'),
                'is_working_day' => $dayOfWeek != Carbon::SUNDAY,
                'attendance_status' => $attendanceStatus,
                'jam_masuk' => $record && $record->jam_masuk ? $record->jam_masuk->format('H:i') : null,
                'jam_keluar' => $record && $record->jam_keluar ? $record->jam_keluar->format('H:i') : null,
                'lokasi' => $record && $record->settingKehadiran ? $record->settingKehadiran->nama_gedung : null,
                'working_hours' => null
            ];

            if ($record && ($record->jam_masuk || $record->jam_keluar)) {
                $dayData['working_hours'] = self::calculateWorkingHours($record);
            }

            // Update summary
            $summary['total_hari']++;
            
            if ($dayOfWeek != Carbon::SUNDAY) {
                $summary['total_hari_kerja']++;
                
                switch ($attendanceStatus['status']) {
                    case 'hadir':
                        $summary['total_hadir']++;
                        if ($record && $record->terlambat) $summary['total_terlambat']++;
                        if ($record && $record->pulang_awal) $summary['total_pulang_awal']++;
                        break;
                    case 'sakit':
                        $summary['total_sakit']++;
                        break;
                    case 'izin':
                        $summary['total_izin']++;
                        break;
                    case 'cuti':
                        $summary['total_cuti']++;
                        break;
                    case 'alpa':
                        $summary['total_alpa']++;
                        break;
                }
            } else {
                $summary['total_libur']++;
                if ($attendanceStatus['status'] == 'hadir_libur') {
                    // Count as overtime work
                }
            }

            $reportData[] = $dayData;
            $currentDate->addDay();
        }

        // Calculate percentages
        if ($summary['total_hari_kerja'] > 0) {
            $summary['persentase_kehadiran'] = round(($summary['total_hadir'] / $summary['total_hari_kerja']) * 100, 2);
            $summary['persentase_ketidakhadiran'] = round((($summary['total_sakit'] + $summary['total_izin'] + $summary['total_alpa'] + $summary['total_cuti']) / $summary['total_hari_kerja']) * 100, 2);
        }

        return [
            'data' => $reportData,
            'summary' => $summary,
            'period' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'start_date_formatted' => $start->translatedFormat('l, j F Y'),
                'end_date_formatted' => $end->translatedFormat('l, j F Y')
            ]
        ];
    }

    /**
     * Format minutes to HH:MM format
     */
    private static function formatMinutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Convert HH:MM format to minutes
     */
    private static function timeToMinutes($time)
    {
        if (!$time || $time === '00:00') return 0;
        
        $parts = explode(':', $time);
        return ((int)$parts[0] * 60) + (int)$parts[1];
    }

    /**
     * Check if date is holiday
     */
    public static function isHoliday($date)
    {
        // Implementation for checking holidays
        // You can add logic to check against holiday table or API
        $carbon = Carbon::parse($date);
        
        // Basic check for weekends
        if ($carbon->dayOfWeek == Carbon::SUNDAY) {
            return true;
        }
        
        // Add more holiday logic here
        // e.g., check against simpeg_hari_libur table
        
        return false;
    }

    /**
     * Get working days in a month
     */
    public static function getWorkingDaysInMonth($year, $month)
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $workingDays = 0;
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            if (!self::isHoliday($currentDate)) {
                $workingDays++;
            }
            $currentDate->addDay();
        }
        
        return $workingDays;
    }

    /**
     * Calculate performance metrics
     */
    public static function calculatePerformanceMetrics($pegawaiId, $year)
    {
        $metrics = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthlyStats = self::calculateMonthlyStats($pegawaiId, $year, $month);
            
            $metrics[] = [
                'bulan' => $month,
                'nama_bulan' => Carbon::create($year, $month, 1)->translatedFormat('F'),
                'kehadiran' => $monthlyStats['persentase_kehadiran'],
                'keterlambatan' => $monthlyStats['total_terlambat'],
                'total_jam_kerja' => self::formatMinutesToTime($monthlyStats['total_jam_kerja_realisasi']),
                'efektivitas' => $monthlyStats['total_hari_kerja'] > 0 ? 
                    round(($monthlyStats['total_jam_kerja_realisasi'] / $monthlyStats['total_jam_kerja_standar']) * 100, 2) : 0
            ];
        }
        
        return $metrics;
    }
}