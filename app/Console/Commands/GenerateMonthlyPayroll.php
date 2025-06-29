<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PayrollService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyPayroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:generate 
                            {--year= : Tahun yang akan diproses (contoh: 2025)} 
                            {--month= : Bulan yang akan diproses (contoh: 6)}
                            {--force : Jalankan paksa meskipun periode sudah selesai (hati-hati!)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan proses pembuatan data payroll bulanan untuk semua pegawai aktif. Secara default, memproses bulan sebelumnya.';

    /**
     * Execute the console command.
     */
    public function handle(PayrollService $payrollService)
    {
        $this->info('Memulai proses pembuatan payroll...');
        Log::info('Scheduler payroll:generate dimulai.');

        try {
            $year = $this->option('year');
            $month = $this->option('month');

            // Jika tahun atau bulan tidak dispesifikkan, proses untuk bulan sebelumnya.
            if (!$year || !$month) {
                $targetDate = Carbon::now()->subMonth();
                $year = $targetDate->year;
                $month = $targetDate->month;
                $this->comment("Tidak ada periode spesifik, memproses untuk bulan sebelumnya: {$year}-{$month}");
            } else {
                 $this->comment("Memproses untuk periode spesifik: {$year}-{$month}");
            }

            // Di sini Anda bisa menambahkan logika untuk mengambil tunjangan/potongan insidentil dari database.
            // Contoh: Mengambil data THR jika bulan yang diproses adalah bulan menjelang lebaran.
            $additionalAllowances = []; 
            $additionalDeductions = [];

            // Panggil service untuk menjalankan logika utama
            $periode = $payrollService->generatePayrollForPeriod($year, $month, $additionalAllowances, $additionalDeductions);
            
            $this->info('Proses pembuatan payroll berhasil diselesaikan!');
            $this->line("-------------------------------------------------");
            $this->line(" Detail Periode:");
            $this->line(" Nama     : <fg=yellow>{$periode->nama_periode}</>");
            $this->line(" Status   : <fg=green>{$periode->status}</>");
            $this->line(" Pegawai  : <fg=yellow>{$periode->penggajianPegawai->count()}</> orang diproses");
            $this->line("-------------------------------------------------");
            Log::info("Scheduler payroll:generate berhasil untuk periode {$year}-{$month}.");

        } catch (Exception $e) {
            $this->error('Proses pembuatan payroll GAGAL!');
            $this->error('Error: ' . $e->getMessage());
            Log::error("Scheduler payroll:generate GAGAL: " . $e->getMessage());
            // Anda bisa menambahkan notifikasi ke admin (misal via email) di sini jika terjadi error.
            return 1; // Return non-zero untuk menandakan error
        }

        return 0; // Return 0 untuk menandakan sukses
    }
}