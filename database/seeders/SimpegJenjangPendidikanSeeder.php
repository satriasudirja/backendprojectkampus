<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SimpegJenjangPendidikanSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        // Data jenjang pendidikan
        $jenjang = [
            [
                'jenjang_singkatan' => 'SD',
                'jenjang_pendidikan' => 'Sekolah Dasar',
                'nama_jenjang_pendidikan_eng' => 'Elementary School',
                'urutan_jenjang_pendidikan' => 1,
                'perguruan_tinggi' => false,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'SMP',
                'jenjang_pendidikan' => 'SMP/ Sederajat',
                'nama_jenjang_pendidikan_eng' => 'Junior High School',
                'urutan_jenjang_pendidikan' => 2,
                'perguruan_tinggi' => false,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'SMA',
                'jenjang_pendidikan' => 'SMA/SMK Sederajat',
                'nama_jenjang_pendidikan_eng' => 'High School',
                'urutan_jenjang_pendidikan' => 3,
                'perguruan_tinggi' => false,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'D1',
                'jenjang_pendidikan' => 'Diploma 1',
                'nama_jenjang_pendidikan_eng' => 'Diploma 1',
                'urutan_jenjang_pendidikan' => 4,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'D2',
                'jenjang_pendidikan' => 'Diploma 2',
                'nama_jenjang_pendidikan_eng' => 'Diploma 2',
                'urutan_jenjang_pendidikan' => 5,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'D3',
                'jenjang_pendidikan' => 'Diploma 3',
                'nama_jenjang_pendidikan_eng' => 'Diploma 3',
                'urutan_jenjang_pendidikan' => 6,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'D4',
                'jenjang_pendidikan' => 'Diploma 4',
                'nama_jenjang_pendidikan_eng' => 'Diploma 4',
                'urutan_jenjang_pendidikan' => 7,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'S1',
                'jenjang_pendidikan' => 'Strata 1',
                'nama_jenjang_pendidikan_eng' => 'Bachelor',
                'urutan_jenjang_pendidikan' => 8,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'Prof',
                'jenjang_pendidikan' => 'Profesi',
                'nama_jenjang_pendidikan_eng' => 'Professional',
                'urutan_jenjang_pendidikan' => 9,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'S2',
                'jenjang_pendidikan' => 'Strata 2',
                'nama_jenjang_pendidikan_eng' => 'Master',
                'urutan_jenjang_pendidikan' => 10,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'MTr',
                'jenjang_pendidikan' => 'S2 Terapan',
                'nama_jenjang_pendidikan_eng' => 'Applied Master',
                'urutan_jenjang_pendidikan' => 11,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'Sp-1',
                'jenjang_pendidikan' => 'Spesialis 1',
                'nama_jenjang_pendidikan_eng' => 'Specialist 1',
                'urutan_jenjang_pendidikan' => 12,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'S3',
                'jenjang_pendidikan' => 'Strata 3',
                'nama_jenjang_pendidikan_eng' => 'Doctorate',
                'urutan_jenjang_pendidikan' => 13,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'DTr',
                'jenjang_pendidikan' => 'S3 Terapan',
                'nama_jenjang_pendidikan_eng' => 'Applied Doctorate',
                'urutan_jenjang_pendidikan' => 14,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'jenjang_singkatan' => 'Sp-2',
                'jenjang_pendidikan' => 'Spesialis 2',
                'nama_jenjang_pendidikan_eng' => 'Specialist 2',
                'urutan_jenjang_pendidikan' => 15,
                'perguruan_tinggi' => true,
                'pasca_sarjana' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ];
        
        // Masukkan data
        DB::table('simpeg_jenjang_pendidikan')->insert($jenjang);
        
        $this->command->info('Berhasil menambahkan ' . count($jenjang) . ' jenjang pendidikan.');
    }
}