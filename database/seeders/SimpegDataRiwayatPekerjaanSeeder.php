<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataRiwayatPekerjaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dummy data untuk riwayat pekerjaan
        $riwayatPekerjaan = [
            [
                'pegawai_id' => 1,
                'bidang_usaha' => 'Pendidikan Tinggi',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Dosen',
                'instansi' => 'Universitas ABC',
                'divisi' => 'Fakultas Teknik',
                'deskripsi' => 'Mengajar mata kuliah Algoritma dan Pemrograman',
                'mulai_bekerja' => '2015-01-01',
                'selesai_bekerja' => '2018-12-31',
                'area_pekerjaan' => false,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 1,
                'bidang_usaha' => 'Teknologi Informasi',
                'jenis_pekerjaan' => 'Part Time',
                'jabatan' => 'Web Developer',
                'instansi' => 'PT. XYZ',
                'divisi' => 'IT Development',
                'deskripsi' => 'Mengembangkan website dan aplikasi web',
                'mulai_bekerja' => '2016-06-01',
                'selesai_bekerja' => '2017-12-31',
                'area_pekerjaan' => true,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 2,
                'bidang_usaha' => 'Pendidikan Tinggi',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Asisten Dosen',
                'instansi' => 'Universitas XYZ',
                'divisi' => 'Fakultas Ekonomi',
                'deskripsi' => 'Membantu dosen dalam persiapan materi dan evaluasi',
                'mulai_bekerja' => '2017-09-01',
                'selesai_bekerja' => '2019-08-31',
                'area_pekerjaan' => false,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 2,
                'bidang_usaha' => 'Konsultan Keuangan',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Financial Analyst',
                'instansi' => 'PT. Konsultan Maju',
                'divisi' => 'Analisis Keuangan',
                'deskripsi' => 'Melakukan analisis keuangan perusahaan',
                'mulai_bekerja' => '2019-10-01',
                'selesai_bekerja' => null, // Masih bekerja sampai sekarang
                'area_pekerjaan' => true,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 3,
                'bidang_usaha' => 'Teknologi Informasi',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'System Analyst',
                'instansi' => 'PT. Sistem Andalan',
                'divisi' => 'System Integration',
                'deskripsi' => 'Menganalisis kebutuhan sistem dan merancang solusi',
                'mulai_bekerja' => '2016-02-01',
                'selesai_bekerja' => '2020-01-31',
                'area_pekerjaan' => true,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 3,
                'bidang_usaha' => 'Pendidikan Tinggi',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Kepala Laboratorium',
                'instansi' => 'Universitas Ibn Khaldun',
                'divisi' => 'Fakultas Teknik',
                'deskripsi' => 'Mengelola laboratorium komputer dan mengajar praktikum',
                'mulai_bekerja' => '2020-03-01',
                'selesai_bekerja' => null, // Masih bekerja sampai sekarang
                'area_pekerjaan' => false,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 4,
                'bidang_usaha' => 'Kesehatan',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Staf Administrasi',
                'instansi' => 'Rumah Sakit Sehat',
                'divisi' => 'Administrasi',
                'deskripsi' => 'Mengelola administrasi pasien dan keuangan',
                'mulai_bekerja' => '2018-01-15',
                'selesai_bekerja' => '2019-12-31',
                'area_pekerjaan' => true,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 4,
                'bidang_usaha' => 'Pendidikan Tinggi',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Staf Akademik',
                'instansi' => 'Universitas Ibn Khaldun',
                'divisi' => 'BAAK',
                'deskripsi' => 'Mengelola administrasi akademik mahasiswa',
                'mulai_bekerja' => '2020-01-15',
                'selesai_bekerja' => null, // Masih bekerja sampai sekarang
                'area_pekerjaan' => false,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 5,
                'bidang_usaha' => 'Riset dan Pengembangan',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Peneliti',
                'instansi' => 'Lembaga Penelitian Nasional',
                'divisi' => 'Penelitian Teknologi',
                'deskripsi' => 'Melakukan penelitian di bidang teknologi informasi',
                'mulai_bekerja' => '2017-07-01',
                'selesai_bekerja' => '2021-06-30',
                'area_pekerjaan' => true,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'pegawai_id' => 5,
                'bidang_usaha' => 'Pendidikan Tinggi',
                'jenis_pekerjaan' => 'Full Time',
                'jabatan' => 'Dosen',
                'instansi' => 'Universitas Ibn Khaldun',
                'divisi' => 'Fakultas Teknik',
                'deskripsi' => 'Mengajar mata kuliah Basis Data dan Sistem Informasi',
                'mulai_bekerja' => '2021-09-01',
                'selesai_bekerja' => null, // Masih bekerja sampai sekarang
                'area_pekerjaan' => false,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
            ],
        ];

        // Tambahkan timestamps
        foreach ($riwayatPekerjaan as &$data) {
            $data['created_at'] = Carbon::now();
            $data['updated_at'] = Carbon::now();
        }

        // Insert data ke database
        DB::table('simpeg_data_riwayat_pekerjaan')->insert($riwayatPekerjaan);
    }
}