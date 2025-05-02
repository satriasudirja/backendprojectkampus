<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegMasterJenisSertifikasi;
use Carbon\Carbon;

class SimpegMasterJenisSertifikasiSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $data = [
            [
                'kode' => 'S1',
                'nama_sertifikasi' => 'Sertifikasi Dosen Profesional',
                'jenis_sertifikasi' => 'Sertifikasi Dosen',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kode' => 'S2',
                'nama_sertifikasi' => 'Sertifikat Seminar Lokal',
                'jenis_sertifikasi' => 'Sertifikasi Profesi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kode' => 'S3',
                'nama_sertifikasi' => 'Sertifikat Seminar Nasional',
                'jenis_sertifikasi' => 'Sertifikasi Profesi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kode' => 'S4',
                'nama_sertifikasi' => 'Sertifikat Seminar Internasional',
                'jenis_sertifikasi' => 'Sertifikasi Profesi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kode' => 'S5',
                'nama_sertifikasi' => 'Sertifikat Seminar Forum Ilmiah Dosen UEU',
                'jenis_sertifikasi' => 'Sertifikasi Profesi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Menggunakan model untuk insert data
        foreach ($data as $item) {
            SimpegMasterJenisSertifikasi::create($item);
        }
    }
}