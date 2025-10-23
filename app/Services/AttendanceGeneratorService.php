<?php

namespace App\Services;

use App\Models\SimpegPegawai;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegJenisKehadiran;
use App\Models\SimpegJamKerja;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceGeneratorService
{
    protected $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
    }

    /**
     * Generate record absensi untuk seluruh pegawai aktif di bulan tertentu
     * Status default: ALPHA (kecuali hari libur)
     * 
     * @param int $tahun
     * @param int $bulan
     * @return array Summary hasil generate
     */
    public function generateMonthlyAttendance(int $tahun, int $bulan): array
    {
        $startTime = microtime(true);
        
        // 1. Validasi bulan
        if ($bulan < 1 || $bulan > 12) {
            throw new \InvalidArgumentException("Bulan tidak valid: {$bulan}");
        }

        // 2. Cek apakah sudah pernah di-generate
        $existingCount = SimpegAbsensiRecord::whereYear('tanggal_absensi', $tahun)
            ->whereMonth('tanggal_absensi', $bulan)
            ->count();

        if ($existingCount > 0) {
            Log::warning("Absensi bulan {$bulan}/{$tahun} sudah pernah di-generate ({$existingCount} records)");
            return [
                'success' => false,
                'message' => "Absensi untuk bulan {$bulan}/{$tahun} sudah pernah di-generate.",
                'stats' => ['existing_records' => $existingCount]
            ];
        }

        // 3. Get Jenis Kehadiran "Alpha"
        $jenisAlpha = SimpegJenisKehadiran::where('kode_jenis', 'A')->first();
        if (!$jenisAlpha) {
            throw new \Exception("Jenis Kehadiran 'Alpha' (kode: A) tidak ditemukan. Jalankan seeder terlebih dahulu.");
        }

        // 4. Get Jam Kerja Default
        $jamKerjaDefault = SimpegJamKerja::where('is_default', true)->first();

        // 5. Get Pegawai Aktif
        $pegawaiAktif = SimpegPegawai::whereHas('statusAktif', function ($query) {
            $query->where('kode', 'AA'); // Status Aktif
        })->get();

        if ($pegawaiAktif->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada pegawai aktif untuk di-generate',
                'stats' => []
            ];
        }

        // 6. Generate Tanggal Kerja (exclude weekend & libur)
        $tanggalKerja = $this->getWorkingDates($tahun, $bulan);

        if (empty($tanggalKerja)) {
            return [
                'success' => false,
                'message' => "Tidak ada hari kerja di bulan {$bulan}/{$tahun}",
                'stats' => []
            ];
        }

        // 7. Bulk Insert (Efisien untuk banyak data)
        $records = [];
        $batchSize = 500; // Insert per 500 records
        $totalGenerated = 0;

        DB::beginTransaction();
        try {
            foreach ($pegawaiAktif as $pegawai) {
                foreach ($tanggalKerja as $tanggal) {
                    $records[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'pegawai_id' => $pegawai->id,
                        'tanggal_absensi' => $tanggal,
                        'jenis_kehadiran_id' => $jenisAlpha->id,
                        'jam_kerja_id' => $jamKerjaDefault?->id,
                        'status_verifikasi' => 'auto_generated',
                        'jam_masuk' => null,
                        'jam_keluar' => null,
                        'terlambat' => false,
                        'pulang_awal' => false,
                        'keterangan' => 'Auto-generated: Menunggu absensi',
                        'check_sum_absensi' => md5($pegawai->id . $tanggal . 'alpha'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Insert per batch untuk performa
                    if (count($records) >= $batchSize) {
                        SimpegAbsensiRecord::insert($records);
                        $totalGenerated += count($records);
                        $records = []; // Reset
                    }
                }
            }

            // Insert sisa records
            if (!empty($records)) {
                SimpegAbsensiRecord::insert($records);
                $totalGenerated += count($records);
            }

            DB::commit();

            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info("Generate Absensi Bulan {$bulan}/{$tahun} Selesai", [
                'total_pegawai' => $pegawaiAktif->count(),
                'total_hari_kerja' => count($tanggalKerja),
                'total_records' => $totalGenerated,
                'execution_time' => "{$executionTime}s"
            ]);

            return [
                'success' => true,
                'message' => "Berhasil generate {$totalGenerated} record absensi untuk {$pegawaiAktif->count()} pegawai",
                'stats' => [
                    'pegawai_count' => $pegawaiAktif->count(),
                    'working_days' => count($tanggalKerja),
                    'total_records' => $totalGenerated,
                    'execution_time' => "{$executionTime}s"
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Generate Absensi Gagal: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Gagal generate absensi: ' . $e->getMessage(),
                'stats' => []
            ];
        }
    }

    /**
     * Get tanggal kerja (exclude weekend & hari libur)
     */
    protected function getWorkingDates(int $tahun, int $bulan): array
    {
        $dates = [];
        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();

        while ($startDate <= $endDate) {
            // Skip Sabtu-Minggu
            if ($startDate->isWeekday()) {
                // Skip Hari Libur Nasional
                if (!$this->holidayService->isHoliday($startDate)) {
                    $dates[] = $startDate->format('Y-m-d');
                }
            }
            $startDate->addDay();
        }

        return $dates;
    }

    /**
     * Cleanup: Hapus record auto-generated yang sudah lewat (opsional)
     */
    public function cleanupOldAutoGeneratedRecords(int $monthsOld = 6): int
    {
        $cutoffDate = Carbon::now()->subMonths($monthsOld);

        $deleted = SimpegAbsensiRecord::where('status_verifikasi', 'auto_generated')
            ->where('tanggal_absensi', '<', $cutoffDate)
            ->whereNull('jam_masuk') // Masih Alpha
            ->delete();

        Log::info("Cleanup old auto-generated records: {$deleted} deleted");

        return $deleted;
    }
}