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
                'jenis_kehadiran_id' => 13,
                'izin_max' => 3,
                'potong_cuti' => false
            ],
            [
                'kode' => '002',
                'jenis_izin' => 'Izin Menikah',
                'jenis_kehadiran_id' => 8,
                'izin_max' => 3,
                'potong_cuti' => false
            ],
            [
                'kode' => '003',
                'jenis_izin' => 'Izin Menikahkan Anak',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '004',
                'jenis_izin' => 'Izin Mengkhitankan Anak',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '005',
                'jenis_izin' => 'Izin Melahirkan',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 90,
                'potong_cuti' => false
            ],
            [
                'kode' => '006',
                'jenis_izin' => 'Izin Istri Melahirkan',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '007',
                'jenis_izin' => 'Izin Anak Sakit',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '008',
                'jenis_izin' => 'Izin Kondisi berduka/keluarga wafat',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 2,
                'potong_cuti' => false
            ],
            [
                'kode' => '009',
                'jenis_izin' => 'Izin Ibadah Haji',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 40,
                'potong_cuti' => false
            ],
            [
                'kode' => '010',
                'jenis_izin' => 'Izin Ibadah Umrah',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 14,
                'potong_cuti' => false
            ],
            [
                'kode' => '999',
                'jenis_izin' => 'Izin Lain-lain',
                  'jenis_kehadiran_id' => 8,
                'izin_max' => 3,
                'potong_cuti' => false
            ],
        ];

        foreach ($jenisIzinData as $data){

       

            SimpegJenisIzin::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'jenis_kehadiran_id' => $data['jenis_kehadiran_id'],
                    'kode' => $data['kode'],
                    'jenis_izin' => $data['jenis_izin'],
                
                    'izin_max' => $data['izin_max'],
                    'potong_cuti' => $data['potong_cuti']
                ]
            );
        }
    }

 }