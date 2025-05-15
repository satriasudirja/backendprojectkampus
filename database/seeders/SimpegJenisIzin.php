<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisIzin;

class JenisIzinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $jenisIzinData = [
            [
                'jenis_kehadiran_id' => 2, // Asumsi ID untuk kategori izin/ketidakhadiran
                'kode' => '001',
                'jenis_izin' => 'Sakit',
                'status_presensi' => 'Sakit',
                'izin_max' => '3',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '002',
                'jenis_izin' => 'Izin Menikah',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '3',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '003',
                'jenis_izin' => 'Izin Menikahkan Anak',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '2',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '004',
                'jenis_izin' => 'Izin Mengkhitankan Anak',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '2',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '005',
                'jenis_izin' => 'Izin Melahirkan',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '90',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '006',
                'jenis_izin' => 'Izin Istri Melahirkan',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '2',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '007',
                'jenis_izin' => 'Izin Anak Sakit',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '2',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '008',
                'jenis_izin' => 'Izin Kondisi berduka/keluarga wafat',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '2',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '009',
                'jenis_izin' => 'Izin Ibadah Haji',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '40',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '010',
                'jenis_izin' => 'Izin Ibadah Umrah',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '14',
                'potong_cuti' => false
            ],
            [
                'jenis_kehadiran_id' => 2,
                'kode' => '999',
                'jenis_izin' => 'Izin Lain-lain',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => '3',
                'potong_cuti' => false
            ],
        ];

        foreach ($jenisIzinData as $data) {
            JenisIzin::updateOrCreate(
                ['kode' => $data['kode']],
                $data
            );
        }
    }
}