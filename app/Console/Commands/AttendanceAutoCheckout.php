<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SimpegAbsensiRecord;
use Carbon\Carbon;

class AttendanceAutoCheckout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:auto-checkout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Melakukan checkout otomatis untuk absensi yang terbuka pada hari sebelumnya pukul 23:59:59.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $kemarin = Carbon::yesterday();
        $this->info("Mencari absensi yang belum checkout untuk tanggal: {$kemarin->toDateString()}");

        $recordsToCheckout = SimpegAbsensiRecord::whereDate('tanggal_absensi', $kemarin)
            ->whereNotNull('jam_masuk')
            ->whereNull('jam_keluar')
            ->get();

        if ($recordsToCheckout->isEmpty()) {
            $this->info("Tidak ada absensi yang perlu di-checkout otomatis.");
            return Command::SUCCESS;
        }

        foreach ($recordsToCheckout as $record) {
            // Tentukan jam keluar otomatis pada 23:59:59 di hari yang sama
            $autoCheckoutTime = $kemarin->copy()->endOfDay();
            $jamMasuk = Carbon::parse($record->jam_masuk);
            $durasiKerja = $autoCheckoutTime->diffInMinutes($jamMasuk);

            $record->update([
                'jam_keluar' => $autoCheckoutTime,
                'keterangan' => trim(($record->keterangan ?? '') . ' | Absen keluar otomatis oleh sistem.'),
                'durasi_kerja' => (int) round($durasiKerja),
                'pulang_awal' => false,
                'durasi_pulang_awal' => 0,
            ]);
            $this->info("Pegawai ID {$record->pegawai_id} berhasil di-checkout.");
        }

        $this->info("Proses auto checkout selesai. Total: " . $recordsToCheckout->count() . " record.");
        return Command::SUCCESS;
    }
}
