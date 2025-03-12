<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisPelanggaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_pelanggaran
        $data = [
            ['kode' => 'P1', 'nama_pelanggaran' => 'Terlambat atau Alpa'],
            ['kode' => 'P2', 'nama_pelanggaran' => 'Peringgatan Ke 1'],
            ['kode' => 'P3', 'nama_pelanggaran' => 'Peringgatan Ke 2'],
            ['kode' => 'P4', 'nama_pelanggaran' => 'Teguran'],
        ];

        // Insert data ke tabel jenis_pelanggaran
        DB::table('jenis_pelanggaran')->insert($data);
    }
}