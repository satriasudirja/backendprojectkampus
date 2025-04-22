<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisSertifikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_sertifikasi
        $data = [
            ['kode' => 'S1', 'jenis_sertifikasi' => 'Sertifikasi Dosen Profesional', 'kategorisertifikasi'  => 'Sertifikasi Dosen'], // Sesuaikan kategorisertifikasi_id dengan ID yang sesuai
            ['kode' => 'S2', 'jenis_sertifikasi' => 'Sertifikat Seminar Lokal', 'kategorisertifikasi'  => 'Sertifikasi Profesi'], // Sesuaikan kategorisertifikasi_id dengan ID yang sesuai
            ['kode' => 'S3', 'jenis_sertifikasi' => 'Sertifikat Seminar Nasional', 'kategorisertifikasi'  => 'Sertifikasi Profesi'], // Sesuaikan kategorisertifikasi_id dengan ID yang sesuai
            ['kode' => 'S4', 'jenis_sertifikasi' => 'Sertifikat Seminar Internasional', 'kategorisertifikasi'  => 'Sertifikasi Profesi'], // Sesuaikan kategorisertifikasi_id dengan ID yang sesuai
            ['kode' => 'S5', 'jenis_sertifikasi' => 'Sertifikat Seminar Forum Ilmiah Dosen UEU', 'kategorisertifikasi' => 'Sertifikasi Profesi'], // Sesuaikan kategorisertifikasi_id dengan ID yang sesuai
        ];

        // Insert data ke tabel jenis_sertifikasi
        DB::table('jenis_sertifikasi')->insert($data);
    }
}