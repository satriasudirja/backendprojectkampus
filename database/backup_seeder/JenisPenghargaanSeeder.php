<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisPenghargaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_penghargaan
        $data = [
            ['kode' => 'P1', 'penghargaan' => 'Emas'],
            ['kode' => 'P2', 'penghargaan' => 'Umroh'],
            ['kode' => 'P3', 'penghargaan' => 'Sertifikat'],
            ['kode' => 'P4', 'penghargaan' => 'Dosen/Pegawai Teladan'],
        ];

        // Insert data ke tabel jenis_penghargaan
        DB::table('jenis_penghargaan')->insert($data);
    }
}