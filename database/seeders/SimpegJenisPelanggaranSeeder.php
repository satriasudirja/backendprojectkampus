<?php

namespace Database\Seeders;

use App\Models\SimpegJenisPelanggaran;
use Illuminate\Database\Seeder;

class SimpegJenisPelanggaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $jenispelanggaran = [
            ['kode' => 'P1','nama_pelanggaran' => 'Terlambat atau Alpa'],
            ['kode' => 'P2','nama_pelanggaran' => 'Peringgatan Ke 1'],
            ['kode' => 'P3','nama_pelanggaran' => 'Peringgatan Ke 2'],
            ['kode' => 'P4','nama_pelanggaran' => 'Teguran'],
        ];

        foreach ($jenispelanggaran as $pelanggaran) {
            SimpegJenisPelanggaran::updateOrCreate(
                ['kode' => $pelanggaran['kode']],
                $pelanggaran
            );
        }
    }
}