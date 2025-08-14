<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJenisPenghargaan;
use Illuminate\Support\Str;

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
            ['id'=>Str::uuid(),'kode' => 'P1', 'nama' => 'Emas'],
            ['id'=>Str::uuid(),'kode' => 'P2', 'nama' => 'Umroh'],
            ['id'=>Str::uuid(),'kode' => 'P3', 'nama' => 'Sertifikat'],
            ['id'=>Str::uuid(),'kode' => 'P4', 'nama' => 'Dosen/Pegawai Teladan'],
        ];

        foreach ($data as $item) {
            SimpegJenisPenghargaan::updateOrcreate($item);
        }
    }
}
