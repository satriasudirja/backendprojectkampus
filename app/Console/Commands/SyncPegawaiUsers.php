<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SimpegUser; // Pastikan namespace model Anda benar
use Illuminate\Support\Facades\DB;

class SyncPegawaiUsers extends Command
{
    protected $signature = 'app:sync-pegawai-users';
    protected $description = 'Synchronize pegawai_id in simpeg_user table from simpeg_pegawai table based on NIP';

    public function handle()
    {
        $this->info('Starting user synchronization...');

        // Ambil semua user yang akan diproses dalam potongan (chunks)
        SimpegUser::query()
            ->whereNotNull('username') // Hanya proses user yang punya NIP
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    // Cari ID pegawai yang sesuai berdasarkan NIP
                    $correctPegawaiId = DB::table('simpeg_pegawai')
                                          ->where('nip', $user->username)
                                          ->value('id');

                    // Jika pegawai ditemukan dan ID-nya tidak cocok
                    if ($correctPegawaiId && $user->pegawai_id != $correctPegawaiId) {
                        $oldId = $user->pegawai_id;
                        $user->pegawai_id = $correctPegawaiId;
                        $user->save(); // Simpan perubahan

                        $this->line("User NIP {$user->nip}: Updated pegawai_id from {$oldId} to {$correctPegawaiId}.");
                    } elseif (!$correctPegawaiId) {
                        $this->warn("User NIP {$user->nip}: No matching employee found in simpeg_pegawai table.");
                    }
                }
                $this->info('Processed a chunk of users...');
            });

        $this->info('Synchronization complete!');
        return 0;
    }
}