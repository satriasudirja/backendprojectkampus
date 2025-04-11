<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

     










        //satria
        $this->call([
            JenisSKSeeder::class,
        ]);
        $this->call([
            GelarAkademikSeeder::class,
        ]);
        $this->call([
            MediaPublikasiSeeder::class,
        ]);
        $this->call([
            JenisSertifikasiSeeder::class,
        ]);
        $this->call([
            JenisTesSeeder::class,
        ]);
        $this->call([
            JenisPKMSeeder::class,
        ]);
        $this->call([
            OutputPenelitianSeeder::class,
        ]);
        $this->call([
            JenisPenghargaanSeeder::class,
        ]);
        $this->call([
            JenisPelanggaranSeeder::class,
        ]);
        $this->call([
            DaftarJenisLuaranSeeder::class,
        ]);
        $this->call([
            JenisPublikasiSeeder::class,
        ]);
        $this->call([
            SimpegUserRoleSeeder::class,
        ]);
        $this->call([
    SimpegUserSeeder::class,     // Kemudian seed user
            // Tambahkan seeder lainnya di sini
        ]);
    }
}
