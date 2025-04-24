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
            ['kode' => 'KH', 'nama_status_aktif' => 'Kontrak Habis', 'status_keluar' => true],
            ['kode' => 'M', 'nama_status_aktif' => 'Meninggal Dunia', 'status_keluar' => true],
            ['kode' => 'M5', 'nama_status_aktif' => 'Mangkir 5 Kali Berturut-turut', 'status_keluar' => true],
            ['kode' => 'MD', 'nama_status_aktif' => 'Mengundurkan diri', 'status_keluar' => true],
            ['kode' => 'PD', 'nama_status_aktif' => 'Pensiun Dini', 'status_keluar' => true],
            ['kode' => 'PH', 'nama_status_aktif' => 'PHK', 'status_keluar' => true],
            ['kode' => 'PL', 'nama_status_aktif' => 'Pelanggaran', 'status_keluar' => true],
            ['kode' => 'PN', 'nama_status_aktif' => 'Pensiun Normal', 'status_keluar' => true],
            ['kode' => 'PS', 'nama_status_aktif' => 'Pernikahan Sesama Karyawan', 'status_keluar' => true],
            ['kode' => 'SB', 'nama_status_aktif' => 'Kesalahan Berat', 'status_keluar' => true],
            ['kode' => 'SP', 'nama_status_aktif' => 'Sakit Berkepanjangan', 'status_keluar' => true],
            ['kode' => 'TA', 'nama_status_aktif' => 'Tidak Aktif', 'status_keluar' => true],
            ['kode' => 'TB', 'nama_status_aktif' => 'Tugas Belajar', 'status_keluar' => false],
            ['kode' => 'TW', 'nama_status_aktif' => 'Ditahan Pihak Berwajib', 'status_keluar' => true],
        ];

        foreach ($data as $item) {
            SimpegStatusAktif::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
