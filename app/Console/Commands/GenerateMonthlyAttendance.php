<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceGeneratorService;
use App\Services\HolidayService;

class GenerateMonthlyAttendance extends Command
{
    protected $signature = 'attendance:generate {tahun?} {bulan?}';
    protected $description = 'Generate monthly attendance records for all active employees';

    public function handle()
    {
        $tahun = $this->argument('tahun') ?? now()->year;
        $bulan = $this->argument('bulan') ?? now()->month;

        $this->info("🚀 Generating attendance for {$bulan}/{$tahun}...");

        $service = new AttendanceGeneratorService(new HolidayService());
        $result = $service->generateMonthlyAttendance($tahun, $bulan);

        if ($result['success']) {
            $this->info("✅ " . $result['message']);
            
            if (!empty($result['stats'])) {
                $this->newLine();
                $this->table(
                    ['Metric', 'Value'],
                    collect($result['stats'])->map(fn($v, $k) => [$k, $v])->values()
                );
            }
        } else {
            $this->error("❌ " . $result['message']);
        }

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}