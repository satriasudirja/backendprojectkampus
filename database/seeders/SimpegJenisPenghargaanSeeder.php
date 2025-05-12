<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJenisPenghargaan;

class SimpegJenisPenghargaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            ['kode' => 'P1', 'nama' => 'Emas'],
            ['kode' => 'P2', 'nama' => 'Umroh'],
            ['kode' => 'P3', 'nama' => 'Sertifikat'],
            ['kode' => 'P4', 'nama' => 'Dosen/Pegawai Teladan'],
        ];

        foreach ($data as $item) {
            SimpegJenisPenghargaan::updateOrcreate($item);
        }
    }
}
