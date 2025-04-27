<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpegDaftarJenisSkSeeder extends Seeder
{
    public function run()
    {
        DB::table('simpeg_daftar_jenis_sk')->insert([
            ['kode' => 'PK', 'jenis_sk' => 'Perjanjian Kontrak'],
            ['kode' => 'SK0', 'jenis_sk' => 'SK Tetap 80%'],
            ['kode' => 'SK1', 'jenis_sk' => 'SK Tetap 100%'],
            ['kode' => 'SK2', 'jenis_sk' => 'SK Inpassing'],
            ['kode' => 'SK3', 'jenis_sk' => 'SK Pangkat'],
            ['kode' => 'SK4', 'jenis_sk' => 'SK Berkala YPIKA'],
            ['kode' => 'SK5', 'jenis_sk' => 'SK Pangkat YPIKA'],
        ]);
    }
}
