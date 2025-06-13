<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJamKerja;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MonitoringPresensiController extends Controller
{
    /**
     * Monitoring presensi dengan filter dan search
     * Default menampilkan data hari ini
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        
        // Filter parameters - default hari ini
        $tanggal = $request->tanggal ?? date('Y-m-d');
        $unitKerjaFilter = $request->unit_kerja;
        $jamKerjaFilter = $request->jam_kerja;
        $statusPresensiFilter = $request->status_presensi;

        // Pastikan ada data absensi untuk hari yang dipilih, jika tidak buat otomatis
        $this->generateDailyAttendanceRecords($tanggal);

        // Base query dengan relasi - simplified
        $query = SimpegAbsensiRecord::with([
            'pegawai.unitKerja',
            'cutiRecord',
            'izinRecord'
            // Removed jamKerja dan jenisKehadiran loading untuk sementara
        ])->whereDate('tanggal_absensi', $tanggal);

        // Filter by unit kerja
        if ($unitKerjaFilter) {
            $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                // Jika filter berupa ID, ambil kode_unit terlebih dahulu
                if (is_numeric($unitKerjaFilter)) {
                    $unitKerja = SimpegUnitKerja::find($unitKerjaFilter);
                    if ($unitKerja) {
                        // Gunakan type casting untuk PostgreSQL
                        $q->whereRaw("unit_kerja_id::text = ?", [$unitKerja->kode_unit]);
                    }
                } else {
                    // Jika bukan numeric, anggap sebagai kode_unit
                    $q->whereRaw("unit_kerja_id::text = ?", [$unitKerjaFilter]);
                }
            });
        }

        // Filter by jam kerja
        if ($jamKerjaFilter) {
            $query->where('jam_kerja_id', $jamKerjaFilter);
        }

        // Filter by status presensi
        if ($statusPresensiFilter) {
            $query->where(function($q) use ($statusPresensiFilter) {
                switch ($statusPresensiFilter) {
                    case 'hadir_lengkap':
                        $q->whereNotNull('jam_masuk')
                          ->whereNotNull('jam_keluar')
                          ->where('terlambat', false)
                          ->where('pulang_awal', false);
                        break;
                    case 'hadir_masuk':
                        $q->whereNotNull('jam_masuk')
                          ->whereNull('jam_keluar');
                        break;
                    case 'terlambat':
                        $q->where('terlambat', true);
                        break;
                    case 'pulang_awal':
                        $q->where('pulang_awal', true);
                        break;
                    case 'alpha':
                        $q->whereNull('jam_masuk')
                          ->whereNull('jam_keluar')
                          ->whereNull('cuti_record_id')
                          ->whereNull('izin_record_id');
                        break;
                    case 'cuti':
                        $q->whereNotNull('cuti_record_id');
                        break;
                    case 'izin':
                        $q->whereNotNull('izin_record_id');
                        break;
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
                // Search by unit kerja - fix type casting untuk PostgreSQL
                ->orWhereHas('pegawai', function($subQ) use ($search) {
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM simpeg_unit_kerja 
                        WHERE simpeg_pegawai.unit_kerja_id::text = simpeg_unit_kerja.kode_unit 
                        AND LOWER(simpeg_unit_kerja.nama_unit) LIKE LOWER(?) 
                        AND simpeg_unit_kerja.deleted_at IS NULL
                    )", ['%'.$search.'%']);
                })
                // Search by keterangan/status - skip jam kerja search karena kolom tidak ada
                ->orWhere('keterangan', 'like', '%'.$search.'%');
            });
        }

        // Order by nama pegawai
        $query->join('simpeg_pegawai', 'simpeg_absensi_record.pegawai_id', '=', 'simpeg_pegawai.id')
              ->orderBy('simpeg_pegawai.nama', 'asc')
              ->select('simpeg_absensi_record.*');

        $presensiData = $query->paginate($perPage);

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
            'data' => $presensiData->map(function ($item) {
                return $this->formatPresensiData($item);
            }),
            'pagination' => [
                'current_page' => $presensiData->currentPage(),
                'per_page' => $presensiData->perPage(),
                'total' => $presensiData->total(),
                'last_page' => $presensiData->lastPage()
            ],
            'filters_applied' => [
                'tanggal' => $tanggal,
                'unit_kerja' => $unitKerjaFilter,
                'jam_kerja' => $jamKerjaFilter,
                'status_presensi' => $statusPresensiFilter,
                'search' => $search
            ]
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Generate attendance records untuk semua pegawai aktif pada tanggal tertentu
     * Jika belum ada record untuk hari tersebut
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

            // Ambil semua pegawai aktif - handle untuk status yang berbeda
            $pegawaiAktif = SimpegPegawai::where(function($query) {
                                            $query->where('status_kerja', 'Aktif')
                                                  ->orWhere('status_kerja', 'LIKE', '%aktif%')
                                                  ->orWhere('status_kerja', 'LIKE', '%AKTIF%');
                                        })
                                       ->orWhereHas('statusAktif', function($q) {
                                           $q->where('nama_status_aktif', 'like', '%aktif%')
                                             ->orWhere('nama_status_aktif', 'like', '%AKTIF%');
                                       })
                                       ->limit(1000) // Batasi untuk menghindari timeout
                                       ->get();

            // Ambil jam kerja default
            $defaultJamKerja = SimpegJamKerja::where('is_default', true)
                                            ->orWhere('is_active', true)
                                            ->first();
            
            // Generate record presensi default (alpha) untuk setiap pegawai
            $records = [];
            foreach ($pegawaiAktif as $pegawai) {
                // Generate checksum untuk data default
                $defaultChecksum = md5($pegawai->id . '|' . $tanggal . '|default');
                
                $record = [
                    'pegawai_id' => $pegawai->id,
                    'tanggal_absensi' => $tanggal,
                    'status_verifikasi' => 'pending',
                    'check_sum_absensi' => $defaultChecksum,
                    'terlambat' => false,
                    'pulang_awal' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                // Tambahkan jam_kerja_id jika ada jam kerja default
                if ($defaultJamKerja) {
                    $record['jam_kerja_id'] = $defaultJamKerja->id;
                }
                
                $records[] = $record;
            }

            // Batch insert untuk performa lebih baik
            if (!empty($records)) {
                // Insert dalam chunk untuk menghindari memory limit
                $chunks = array_chunk($records, 100);
                foreach ($chunks as $chunk) {
                    SimpegAbsensiRecord::insert($chunk);
                }
            }
        } catch (\Exception $e) {
            // Log error tapi jangan stop execution
            \Log::error('Error generating daily attendance records: ' . $e->getMessage());
            // Continue dengan data yang ada (jika ada)
        }
    }

    /**
     * Get summary statistics untuk tanggal tertentu
     */
    private function getSummaryStatistics($tanggal, $unitKerja = null)
    {
        $baseQuery = SimpegAbsensiRecord::whereDate('tanggal_absensi', $tanggal);
        
        if ($unitKerja) {
            $baseQuery->whereHas('pegawai', function($q) use ($unitKerja) {
                // Jika filter berupa ID, ambil kode_unit terlebih dahulu
                if (is_numeric($unitKerja)) {
                    $unitKerjaObj = SimpegUnitKerja::find($unitKerja);
                    if ($unitKerjaObj) {
                        // Gunakan type casting untuk PostgreSQL
                        $q->whereRaw("unit_kerja_id::text = ?", [$unitKerjaObj->kode_unit]);
                    }
                } else {
                    // Jika bukan numeric, anggap sebagai kode_unit
                    $q->whereRaw("unit_kerja_id::text = ?", [$unitKerja]);
                }
            });
        }

        $total = $baseQuery->count();
        $hadir = $baseQuery->clone()->whereNotNull('jam_masuk')->count();
        $alpha = $baseQuery->clone()->whereNull('jam_masuk')->whereNull('jam_keluar')
                                   ->whereNull('cuti_record_id')->whereNull('izin_record_id')->count();
        $terlambat = $baseQuery->clone()->where('terlambat', true)->count();
        $cuti = $baseQuery->clone()->whereNotNull('cuti_record_id')->count();
        $izin = $baseQuery->clone()->whereNotNull('izin_record_id')->count();

        return [
            'total_pegawai' => $total,
            'hadir' => $hadir,
            'alpha' => $alpha,
            'terlambat' => $terlambat,
            'cuti' => $cuti,
            'izin' => $izin,
            'persentase_kehadiran' => $total > 0 ? round(($hadir / $total) * 100, 2) : 0
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
                                                  'value' => $unit->id, // Gunakan ID sebagai value untuk filter
                                                  'label' => $unit->nama_unit
                                              ];
                                          }),
            'status_presensi' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => 'hadir_lengkap', 'label' => 'Hadir Lengkap'],
                ['value' => 'hadir_masuk', 'label' => 'Hadir Masuk'],
                ['value' => 'terlambat', 'label' => 'Terlambat'],
                ['value' => 'pulang_awal', 'label' => 'Pulang Awal'],
                ['value' => 'alpha', 'label' => 'Alpha'],
                ['value' => 'cuti', 'label' => 'Cuti'],
                ['value' => 'izin', 'label' => 'Izin']
            ]
            // Removed jam_kerja filter karena kolom nama_jam_kerja tidak ada
        ];
    }

    /**
     * Format presensi data untuk tabel sesuai requirement
     */
    private function formatPresensiData($presensi)
    {
        $attendanceStatus = $presensi->getAttendanceStatus();
        
        // Format jam kerja - simplified karena struktur tabel tidak pasti
        $jamKerja = '';
        if ($presensi->jamKerja) {
            // Coba beberapa kemungkinan nama kolom
            $jamKerja = $presensi->jamKerja->nama ?? 
                       $presensi->jamKerja->name ?? 
                       $presensi->jamKerja->nama_jam_kerja ?? 
                       'Jam Kerja ID: ' . $presensi->jam_kerja_id;
        } else {
            $jamKerja = 'Jam Kerja Default';
        }

        // Format kehadiran
        $kehadiran = '';
        if ($presensi->jam_masuk && $presensi->jam_keluar) {
            $jamMasuk = Carbon::parse($presensi->jam_masuk)->format('H:i');
            $jamKeluar = Carbon::parse($presensi->jam_keluar)->format('H:i');
            $kehadiran = "Masuk: {$jamMasuk}, Keluar: {$jamKeluar}";
            
            if ($presensi->terlambat) {
                $kehadiran .= ' (Terlambat)';
            }
            if ($presensi->pulang_awal) {
                $kehadiran .= ' (Pulang Awal)';
            }
        } elseif ($presensi->jam_masuk) {
            $jamMasuk = Carbon::parse($presensi->jam_masuk)->format('H:i');
            $kehadiran = "Masuk: {$jamMasuk}";
            if ($presensi->terlambat) {
                $kehadiran .= ' (Terlambat)';
            }
        } elseif ($presensi->cutiRecord) {
            $kehadiran = 'Sedang Cuti';
        } elseif ($presensi->izinRecord) {
            $kehadiran = 'Sedang Izin';
        } else {
            $kehadiran = '-';
        }
        
        return [
            'nip' => $presensi->pegawai->nip,
            'nama_pegawai' => $presensi->pegawai->nama,
            'unit_kerja' => $presensi->pegawai->unitKerja->nama_unit ?? '-',
            'jam_kerja' => $jamKerja,
            'kehadiran' => $kehadiran,
            'status' => $attendanceStatus['label'],
            'status_color' => $attendanceStatus['color'],
            'detail' => [
                'id' => $presensi->id,
                'tanggal_absensi' => $presensi->tanggal_absensi->format('Y-m-d'),
                'jam_masuk' => $presensi->jam_masuk ? Carbon::parse($presensi->jam_masuk)->format('H:i:s') : null,
                'jam_keluar' => $presensi->jam_keluar ? Carbon::parse($presensi->jam_keluar)->format('H:i:s') : null,
                'terlambat' => $presensi->terlambat,
                'pulang_awal' => $presensi->pulang_awal,
                'durasi_kerja' => $presensi->getFormattedWorkingDuration(),
                'status_verifikasi' => $presensi->status_verifikasi,
                'keterangan' => $presensi->keterangan
            ]
        ];
    }
}