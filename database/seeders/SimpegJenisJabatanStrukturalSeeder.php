<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisJabatanStruktural;

class SimpegJenisJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode' => '10000', 'jenis_jabatan_struktural' => 'Rektor'],
            ['kode' => '11000', 'jenis_jabatan_struktural' => 'Wakil Rektor I'],
            ['kode' => '11001', 'jenis_jabatan_struktural' => 'Wakil Rektor II'],
            ['kode' => '11100', 'jenis_jabatan_struktural' => 'Wakil Rektor III'],
            ['kode' => '11101', 'jenis_jabatan_struktural' => 'Wakil Rektor IV'],
            ['kode' => '11200', 'jenis_jabatan_struktural' => 'Direktur Pascasarjana'],
            ['kode' => '11300', 'jenis_jabatan_struktural' => 'Kepala Lembaga'],
            ['kode' => '11400', 'jenis_jabatan_struktural' => 'Ketua Unit'],
            ['kode' => '12000', 'jenis_jabatan_struktural' => 'Dekan'],
            ['kode' => '12100', 'jenis_jabatan_struktural' => 'Ketua Lembaga'],
            ['kode' => '12101', 'jenis_jabatan_struktural' => 'Ketua Senat Universitas'],
            ['kode' => '12110', 'jenis_jabatan_struktural' => 'Ketua Program Studi'],
            ['kode' => '12111', 'jenis_jabatan_struktural' => 'Sekertaris Program Studi'],
            ['kode' => '12112', 'jenis_jabatan_struktural' => 'Wakil Dekan I'],
            ['kode' => '12120', 'jenis_jabatan_struktural' => 'Wakil Dekan II'],
            ['kode' => '12130', 'jenis_jabatan_struktural' => 'Wakil Dekan III'],
            ['kode' => '12140', 'jenis_jabatan_struktural' => 'Wakil Direktur Pascasarjana'],
            ['kode' => '12150', 'jenis_jabatan_struktural' => 'Sekretaris Lembaga'],
            ['kode' => '12160', 'jenis_jabatan_struktural' => 'Kepala Laboratorium'],
            ['kode' => '12170', 'jenis_jabatan_struktural' => 'Ketua Senat Fakultas'],
            ['kode' => '12180', 'jenis_jabatan_struktural' => 'Lainnya'],
            ['kode' => '12190', 'jenis_jabatan_struktural' => 'Asisten'],
            ['kode' => '12200', 'jenis_jabatan_struktural' => 'Kepala Badan'],
            ['kode' => '12210', 'jenis_jabatan_struktural' => 'Sekretaris Badan'],
            ['kode' => '20000', 'jenis_jabatan_struktural' => 'Staff / Karyawan / Satpam'],
            ['kode' => '21000', 'jenis_jabatan_struktural' => 'Sekretaris Staff'],
            ['kode' => '21009', 'jenis_jabatan_struktural' => 'Kepala Bidang'],
            ['kode' => '21010', 'jenis_jabatan_struktural' => 'Sekretaris Bidang'],
        ];

        foreach ($data as $item) {
            JenisJabatanStruktural::create($item);
        }
    }
}