<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJabatanStruktural;
use Carbon\Carbon;

class SimpegJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        $data = [
            // Rektor dan Wakil Rektor
            [
                'kode' => '001',
                'singkatan' => 'REK',
                'unit_kerja_id' => 1, // Rektorat
                'jenis_jabatan_struktural_id' => 1, // Rektor
                'pangkat_id' => 17, // IV/E
                'eselon_id' => 1, // I
                'alamat_email' => 'rektor@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => null,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '002',
                'singkatan' => 'WRA1',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 2, // Wakil Rektor
                'pangkat_id' => 16, // IV/D
                'eselon_id' => 2, // II
                'alamat_email' => 'wrakademik@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '003',
                'singkatan' => 'WRSDA',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 2,
                'pangkat_id' => 16,
                'eselon_id' => 2,
                'alamat_email' => 'wrsda@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '004',
                'singkatan' => 'WRKD',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 2,
                'pangkat_id' => 16,
                'eselon_id' => 2,
                'alamat_email' => 'wrkd@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '005',
                'singkatan' => 'WRKIP',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 2,
                'pangkat_id' => 16,
                'eselon_id' => 2,
                'alamat_email' => 'wrkip@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '006',
                'singkatan' => 'SEKREK',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 3, // Sekretaris
                'pangkat_id' => 15, // IV/C
                'eselon_id' => 2,
                'alamat_email' => 'sekretaris.rektor@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],

            // Staf Ahli
            [
                'kode' => '007',
                'singkatan' => 'SAR-AK',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 5, // Staf Ahli
                'pangkat_id' => 15, // IV/C
                'eselon_id' => 3, // III
                'alamat_email' => 'stafahli.akademik@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => false,
                'aktif' => true,
                'parent_jabatan' => '002',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '008',
                'singkatan' => 'SAR-KD',
                'unit_kerja_id' => 1,
                'jenis_jabatan_struktural_id' => 5,
                'pangkat_id' => 15,
                'eselon_id' => 3,
                'alamat_email' => 'stafahli.kemahasiswaan@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => false,
                'aktif' => true,
                'parent_jabatan' => '004',
                'created_at' => $now,
                'updated_at' => $now
            ],

            // Biro Administrasi Akademik dan Kemahasiswaan
            [
                'kode' => '009',
                'singkatan' => 'KABAAK',
                'unit_kerja_id' => 2, // Biro AAK
                'jenis_jabatan_struktural_id' => 6, // Kepala Biro
                'pangkat_id' => 15, // IV/C
                'eselon_id' => 3,
                'alamat_email' => 'kabiro.aak@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '010',
                'singkatan' => 'KABAG-PEND',
                'unit_kerja_id' => 2,
                'jenis_jabatan_struktural_id' => 7, // Kepala Bagian
                'pangkat_id' => 14, // IV/B
                'eselon_id' => 4,
                'alamat_email' => 'kabag.pendidikan@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '009',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '011',
                'singkatan' => 'KABAG-KMA',
                'unit_kerja_id' => 2,
                'jenis_jabatan_struktural_id' => 7,
                'pangkat_id' => 14,
                'eselon_id' => 4,
                'alamat_email' => 'kabag.kemahasiswaan@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '009',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '012',
                'singkatan' => 'KASUBAG-PEND',
                'unit_kerja_id' => 2,
                'jenis_jabatan_struktural_id' => 8, // Kepala Sub Bagian
                'pangkat_id' => 13, // IV/A
                'eselon_id' => 5,
                'alamat_email' => 'kasubag.pendidikan@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => false,
                'aktif' => true,
                'parent_jabatan' => '010',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '013',
                'singkatan' => 'KASUBAG-KMA',
                'unit_kerja_id' => 2,
                'jenis_jabatan_struktural_id' => 8,
                'pangkat_id' => 13,
                'eselon_id' => 5,
                'alamat_email' => 'kasubag.kemahasiswaan@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => false,
                'aktif' => true,
                'parent_jabatan' => '011',
                'created_at' => $now,
                'updated_at' => $now
            ],

            // Biro Administrasi Keuangan dan Kerjasama
            [
                'kode' => '018',
                'singkatan' => 'KABAKK',
                'unit_kerja_id' => 3, // Biro AKK
                'jenis_jabatan_struktural_id' => 6,
                'pangkat_id' => 15,
                'eselon_id' => 3,
                'alamat_email' => 'kabiro.akk@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '019',
                'singkatan' => 'KABAG-KEU',
                'unit_kerja_id' => 3,
                'jenis_jabatan_struktural_id' => 7,
                'pangkat_id' => 14,
                'eselon_id' => 4,
                'alamat_email' => 'kabag.keuangan@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '018',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '022',
                'singkatan' => 'KABAG-KPG',
                'unit_kerja_id' => 3,
                'jenis_jabatan_struktural_id' => 7,
                'pangkat_id' => 14,
                'eselon_id' => 4,
                'alamat_email' => 'kabag.kepegawaian@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '018',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '024',
                'singkatan' => 'KABAG-SEK',
                'unit_kerja_id' => 3,
                'jenis_jabatan_struktural_id' => 7,
                'pangkat_id' => 14,
                'eselon_id' => 4,
                'alamat_email' => 'kabag.sekretariat@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '018',
                'created_at' => $now,
                'updated_at' => $now
            ],

            // Biro Administrasi Pelaporan dan Sistem Informasi
            [
                'kode' => '014',
                'singkatan' => 'KABAPSI',
                'unit_kerja_id' => 4, // Biro APSI
                'jenis_jabatan_struktural_id' => 6,
                'pangkat_id' => 15,
                'eselon_id' => 3,
                'alamat_email' => 'kabiro.apsi@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '001',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '015',
                'singkatan' => 'KABAG-SI',
                'unit_kerja_id' => 4,
                'jenis_jabatan_struktural_id' => 7,
                'pangkat_id' => 14,
                'eselon_id' => 4,
                'alamat_email' => 'kabag.si@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => true,
                'aktif' => true,
                'parent_jabatan' => '014',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'kode' => '017',
                'singkatan' => 'PROG',
                'unit_kerja_id' => 4,
                'jenis_jabatan_struktural_id' => 9, // Staff
                'pangkat_id' => 11, // III/B
                'eselon_id' => 6,
                'alamat_email' => 'programmer@uika-bogor.ac.id',
                'beban_sks' => 0,
                'is_pimpinan' => false,
                'aktif' => true,
                'parent_jabatan' => '015',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];

        SimpegJabatanStruktural::insert($data);
    }
}