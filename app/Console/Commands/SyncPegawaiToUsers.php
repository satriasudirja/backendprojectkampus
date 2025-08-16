<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SimpegPegawai;
use App\Models\SimpegUser;
use Illuminate\Support\Facades\Hash;

class SyncPegawaiToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simpeg:sync-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create user accounts for employees who do not have one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting synchronization: Looking for employees without user accounts...');

        // 1. Cari semua pegawai yang BELUM memiliki relasi ke tabel users.
        // Ini adalah cara paling efisien untuk menemukan data yang hilang.
        $pegawaiWithoutUsers = SimpegPegawai::whereDoesntHave('user')->get();

        if ($pegawaiWithoutUsers->isEmpty()) {
            $this->info('All employees already have user accounts. No synchronization needed.');
            return 0;
        }

        $this->info($pegawaiWithoutUsers->count() . ' employees found without user accounts. Creating accounts now...');

        // Buat progress bar untuk memberikan feedback visual
        $progressBar = $this->output->createProgressBar($pegawaiWithoutUsers->count());
        $progressBar->start();

        foreach ($pegawaiWithoutUsers as $pegawai) {
            // 2. Buat user baru untuk setiap pegawai yang ditemukan
            SimpegUser::create([
                'pegawai_id' => $pegawai->id,
                'username'   => $pegawai->nip, // Username diambil dari NIP
                'password'   => Hash::make(date('dmY', strtotime($pegawai->tanggal_lahir))), // Password default dari tanggal lahir
                'is_active'  => true,
            ]);

            // Majukan progress bar
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2); // Beri spasi
        $this->info('Synchronization complete! ' . $pegawaiWithoutUsers->count() . ' new user accounts created.');

        return 0;
    }
}
