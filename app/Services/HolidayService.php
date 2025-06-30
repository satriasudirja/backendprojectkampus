<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class HolidayService
{
    protected $baseUrl = 'https://libur.deno.dev/api';

    /**
     * Mengambil data hari libur dari API dan menyimpannya di cache selama 24 jam.
     *
     * @param int $year
     * @return array Array berisi tanggal-tanggal libur dalam format 'Y-m-d'.
     */
    public function getHolidays(int $year): array
    {
        $cacheKey = "holidays_{$year}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($year) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}?year={$year}");

                if ($response->successful() && is_array($response->json())) {
                    // Ambil hanya field 'date' untuk mempermudah pengecekan
                    return collect($response->json())->pluck('date')->toArray();
                }
            } catch (\Exception $e) {
                // Jika API gagal, catat error dan kembalikan array kosong
                Log::error("Gagal mengambil data hari libur untuk tahun {$year}: " . $e->getMessage());
                return [];
            }
            // Jika response tidak sukses atau format tidak sesuai
            return [];
        });
    }

    /**
     * Mengecek apakah sebuah tanggal adalah hari libur (Minggu atau libur nasional).
     *
     * @param Carbon $date
     * @return bool
     */
    public function isHoliday(Carbon $date): bool
    {
        // Hari Minggu selalu dianggap libur
        if ($date->isSunday()) {
            return true;
        }

        // Ambil data libur nasional dari cache/API
        $holidays = $this->getHolidays($date->year);
        
        // Cek apakah tanggal ada di dalam array libur nasional
        return in_array($date->toDateString(), $holidays);
    }

    /**
     * Menghitung jumlah hari kerja dalam rentang tanggal tertentu.
     * Hari kerja adalah semua hari KECUALI hari Minggu dan hari libur nasional.
     *
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     * @return int
     */
    public function calculateWorkingDays($startDate, $endDate): int
    {
        try {
            $period = CarbonPeriod::create(Carbon::parse($startDate), Carbon::parse($endDate));
            $workingDays = 0;
            foreach ($period as $date) {
                if (!$this->isHoliday(clone $date)) {
                    $workingDays++;
                }
            }
            return $workingDays;
        } catch (\Exception $e) {
            Log::error("Error saat kalkulasi hari kerja: " . $e->getMessage());
            return 0;
        }
    }
}
