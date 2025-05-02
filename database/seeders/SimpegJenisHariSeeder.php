<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisHari;

class SimpegJenisHariSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'kode' => '0',
                'nama_hari' => 'Minggu',
                'jenis_hari' => false, // Non Efektif
            ],
            [
                'kode' => '1',
                'nama_hari' => 'Senin',
                'jenis_hari' => true, // Efektif
            ],
            [
                'kode' => '2',
                'nama_hari' => 'Selasa',
                'jenis_hari' => true,
            ],
            [
                'kode' => '3',
                'nama_hari' => 'Rabu',
                'jenis_hari' => true,
            ],
            [
                'kode' => '4',
                'nama_hari' => 'Kamis',
                'jenis_hari' => true,
            ],
            [
                'kode' => '5',
                'nama_hari' => 'Jumat',
                'jenis_hari' => true,
            ],
            [
                'kode' => '6',
                'nama_hari' => 'Sabtu',
                'jenis_hari' => true,
            ],
        ];

        foreach ($data as $item) {
          JenisHari::create($item);
        }
    }
}
