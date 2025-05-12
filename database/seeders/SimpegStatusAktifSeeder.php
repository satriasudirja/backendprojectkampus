<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegStatusAktif;

class SimpegStatusAktifSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['kode' => 'AA', 'nama_status_aktif' => 'Aktif', 'status_keluar' => false],
            ['kode' => 'CL', 'nama_status_aktif' => 'Cuti Luar Tanggungan', 'status_keluar' => false],
            ['kode' => 'TB', 'nama_status_aktif' => 'Tugas Belajar', 'status_keluar' => false],
            ['kode' => 'PN', 'nama_status_aktif' => 'Pensiun Normal', 'status_keluar' => true],
            ['kode' => 'PD', 'nama_status_aktif' => 'Pensiun Dini', 'status_keluar' => true],
            ['kode' => 'M',  'nama_status_aktif' => 'Meninggal Dunia', 'status_keluar' => true],
            ['kode' => 'PH', 'nama_status_aktif' => 'PHK', 'status_keluar' => true],
            ['kode' => 'KH', 'nama_status_aktif' => 'Kontrak Habis', 'status_keluar' => true],
        ];

        foreach ($data as $item) {
            SimpegStatusAktif::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
