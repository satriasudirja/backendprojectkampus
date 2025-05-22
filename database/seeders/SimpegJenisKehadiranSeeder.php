<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJenisKehadiran;
use Carbon\Carbon;

class SimpegJenisKehadiranSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode_jenis' => 'A', 'nama_jenis' => 'Alpha'],
            ['kode_jenis' => 'B', 'nama_jenis' => 'Data belum dimasukkan'],
            ['kode_jenis' => 'C', 'nama_jenis' => 'Cuti'],
            ['kode_jenis' => 'D', 'nama_jenis' => 'Dinas'],
            ['kode_jenis' => 'DK', 'nama_jenis' => 'Tidak Absen Datang'],
            ['kode_jenis' => 'H', 'nama_jenis' => 'Hadir'],
            ['kode_jenis' => 'HL', 'nama_jenis' => 'Hadir Libur'],
            ['kode_jenis' => 'I', 'nama_jenis' => 'Izin Tidak Masuk'],
            ['kode_jenis' => 'L', 'nama_jenis' => 'Libur'],
            ['kode_jenis' => 'P', 'nama_jenis' => 'Perubahan Hari Kerja'],
            ['kode_jenis' => 'PD', 'nama_jenis' => 'Pulang Awal'],
            ['kode_jenis' => 'PK', 'nama_jenis' => 'Tidak Absen Pulang'],
            ['kode_jenis' => 'S', 'nama_jenis' => 'Sakit'],
            ['kode_jenis' => 'T', 'nama_jenis' => 'Terlambat'],
        ];

        foreach ($data as $item) {
            SimpegJenisKehadiran::updateOrCreate(
                ['kode_jenis' => $item['kode_jenis']],
                $item
            );
        }
    }
}