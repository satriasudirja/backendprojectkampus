<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisSKSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_sk
        $data = [
            ['kode' => 'PK', 'jenis_sk' => 'Perjanjian Kontrak'],
            ['kode' => 'SK0', 'jenis_sk' => 'SK Tetap 80%'],
            ['kode' => 'SK1', 'jenis_sk' => 'SK Tetap 100%'],
            ['kode' => 'SK2', 'jenis_sk' => 'SK Inpassing'],
            ['kode' => 'SK3', 'jenis_sk' => 'SK Pangkat'],
            ['kode' => 'SK4', 'jenis_sk' => 'SK Berkala YPIKA'],
            ['kode' => 'SK5', 'jenis_sk' => 'SK Pangkat YPIKA'],
        ];

        // Insert data ke tabel jenis_sk
        DB::table('jenis_sk')->insert($data);
    }
}