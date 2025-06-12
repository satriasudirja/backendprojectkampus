<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJenisKehadiran;

class SimpegJenisKehadiranSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode_jenis' => 'A',  'nama_jenis' => 'Alpha',                  'warna' => 'FF0000'],
            ['kode_jenis' => 'B',  'nama_jenis' => 'Data belum dimasukkan', 'warna' => '93938C'],
            ['kode_jenis' => 'C',  'nama_jenis' => 'Cuti',                   'warna' => '003399'],
            ['kode_jenis' => 'D',  'nama_jenis' => 'Dinas',                  'warna' => '003399'],
            ['kode_jenis' => 'DK', 'nama_jenis' => 'Tidak Absen Datang',    'warna' => 'B073FF'],
            ['kode_jenis' => 'H',  'nama_jenis' => 'Hadir',                  'warna' => '00CC00'],
            ['kode_jenis' => 'HL', 'nama_jenis' => 'Hadir Libur',           'warna' => '999900'],
            ['kode_jenis' => 'I',  'nama_jenis' => 'Izin Tidak Masuk',      'warna' => 'FFCC66'],
            ['kode_jenis' => 'L',  'nama_jenis' => 'Libur',                  'warna' => 'B8B8B8'],
            ['kode_jenis' => 'P',  'nama_jenis' => 'Perubahan Hari Kerja',  'warna' => 'CCFF00'],
            ['kode_jenis' => 'PD', 'nama_jenis' => 'Pulang Awal',           'warna' => 'FF8F1F'],
            ['kode_jenis' => 'PK', 'nama_jenis' => 'Tidak Absen Pulang',    'warna' => '7AD7FF'],
            ['kode_jenis' => 'S',  'nama_jenis' => 'Sakit',                  'warna' => 'CCFF66'],
            ['kode_jenis' => 'T',  'nama_jenis' => 'Terlambat',              'warna' => 'FF6600'],
        ];

        foreach ($data as $item) {
            SimpegJenisKehadiran::updateOrCreate(
                ['kode_jenis' => $item['kode_jenis']],
                $item
            );
        }
    }
}
