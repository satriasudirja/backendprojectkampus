<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpegGajiKomponenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Opsi untuk membersihkan data lama terlebih dahulu jika diperlukan
        // Hapus komentar jika ingin menghapus semua data terlebih dahulu
        // DB::table('simpeg_gaji_komponen')->truncate();

        // Data komponen gaji yang akan dimasukkan
        $komponenGaji = [
            [
                'kode_komponen' => 'T001',
                'nama_komponen' => 'Gaji Pokok',
                'jenis' => 'tunjangan',
                'rumus' => null,
            ],
            [
                'kode_komponen' => 'T002',
                'nama_komponen' => 'Tunjangan Jabatan',
                'jenis' => 'tunjangan',
                'rumus' => null,
            ],
            [
                'kode_komponen' => 'T003',
                'nama_komponen' => 'Tunjangan Keluarga',
                'jenis' => 'tunjangan',
                'rumus' => 'gaji_pokok * 0.1',
            ],
            [
                'kode_komponen' => 'T004',
                'nama_komponen' => 'Tunjangan Makan',
                'jenis' => 'tunjangan',
                'rumus' => null,
            ],
            [
                'kode_komponen' => 'T005',
                'nama_komponen' => 'Tunjangan Transport',
                'jenis' => 'tunjangan',
                'rumus' => null,
            ],
            [
                'kode_komponen' => 'P001',
                'nama_komponen' => 'Potongan PPh 21',
                'jenis' => 'potongan',
                'rumus' => 'gaji_bruto * 0.05',
            ],
            [
                'kode_komponen' => 'P002',
                'nama_komponen' => 'Potongan BPJS Kesehatan',
                'jenis' => 'potongan',
                'rumus' => 'gaji_pokok * 0.01',
            ],
            [
                'kode_komponen' => 'P003',
                'nama_komponen' => 'Potongan BPJS Ketenagakerjaan',
                'jenis' => 'potongan',
                'rumus' => 'gaji_pokok * 0.02',
            ],
            [
                'kode_komponen' => 'P004',
                'nama_komponen' => 'Potongan Koperasi',
                'jenis' => 'potongan',
                'rumus' => null,
            ],
            [
                'kode_komponen' => 'B001',
                'nama_komponen' => 'Tunjangan Hari Raya',
                'jenis' => 'benefit',
                'rumus' => 'gaji_pokok * 1',
            ],
            [
                'kode_komponen' => 'B002',
                'nama_komponen' => 'Bonus Tahunan',
                'jenis' => 'benefit',
                'rumus' => null,
            ],
            [
                'kode_komponen' => 'B003',
                'nama_komponen' => 'Insentif Kinerja',
                'jenis' => 'benefit',
                'rumus' => null,
            ],
        ];

        // Iterasi setiap komponen dan gunakan updateOrInsert untuk menghindari duplikasi
        foreach ($komponenGaji as $komponen) {
            DB::table('simpeg_gaji_komponen')->updateOrInsert(
                ['kode_komponen' => $komponen['kode_komponen']], // Kunci pencarian
                [
                    'nama_komponen' => $komponen['nama_komponen'],
                    'jenis' => $komponen['jenis'],
                    'rumus' => $komponen['rumus'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}