<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJenisIzin;
use App\Models\SimpegJenisKehadiran;

class SimpegJenisIzinSeeder extends Seeder
{
    public function run()
    {
        $jenisIzinData = [
            [
                'kode' => '001',
                'jenis_izin' => 'Sakit',
                'status_presensi' => 'Sakit',
                'izin_max' => 3,
                'potong_cuti' => false
            ],
            [
                'kode' => '002',
                'jenis_izin' => 'Izin Menikah',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 3,
                'potong_cuti' => false
            ],
            [
                'kode' => '003',
                'jenis_izin' => 'Izin Menikahkan Anak',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '004',
                'jenis_izin' => 'Izin Mengkhitankan Anak',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '005',
                'jenis_izin' => 'Izin Melahirkan',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 90,
                'potong_cuti' => false
            ],
            [
                'kode' => '006',
                'jenis_izin' => 'Izin Istri Melahirkan',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '007',
                'jenis_izin' => 'Izin Anak Sakit',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '008',
                'jenis_izin' => 'Izin Kondisi berduka/keluarga wafat',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '009',
                'jenis_izin' => 'Izin Ibadah Haji',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 40,
                'potong_cuti' => false
            ],
            [
                'kode' => '010',
                'jenis_izin' => 'Izin Ibadah Umrah',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 14,
                'potong_cuti' => false
            ],
            [
                'kode' => '999',
                'jenis_izin' => 'Izin Lain-lain',
                'status_presensi' => 'Izin Tidak Masuk',
                'izin_max' => 3,
                'potong_cuti' => false
            ],
        ];

        foreach ($jenisIzinData as $data) {
            $jenisKehadiran = SimpegJenisKehadiran::where('nama_jenis', $data['status_presensi'])->first();

            if (!$jenisKehadiran) {
                echo "❌ Tidak ditemukan jenis kehadiran: {$data['status_presensi']}\n";
                continue;
            }

            SimpegJenisIzin::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'jenis_kehadiran_id' => $jenisKehadiran->id,
                    'kode' => $data['kode'],
                    'jenis_izin' => $data['jenis_izin'],
                    'status_presensi' => $data['status_presensi'],
                    'izin_max' => $data['izin_max'],
                    'potong_cuti' => $data['potong_cuti']
                ]
            );
        }
    }
}
