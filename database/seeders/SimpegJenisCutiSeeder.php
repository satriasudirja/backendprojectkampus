<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegDaftarCuti;

class SimpegJenisCutiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisCutis = [
            [
                'kode' => 'B',
                'nama_jenis_cuti' => 'Besar',
                'standar_cuti' => 6,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
            [
                'kode' => 'CL',
                'nama_jenis_cuti' => 'Cuti diluar Tanggungan',
                'standar_cuti' => 939,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
            [
                'kode' => 'H',
                'nama_jenis_cuti' => 'Haji',
                'standar_cuti' => 40,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
            [
                'kode' => 'M',
                'nama_jenis_cuti' => 'Melahirkan',
                'standar_cuti' => 90,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
            [
                'kode' => 'ME',
                'nama_jenis_cuti' => 'Menikah',
                'standar_cuti' => 10,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
            [
                'kode' => 'S',
                'nama_jenis_cuti' => 'Sakit',
                'standar_cuti' => 11,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
            [
                'kode' => 'THN',
                'nama_jenis_cuti' => 'Cuti Tahunan',
                'standar_cuti' => 12,
                'format_nomor_surat' => '{{kode}}/{{urutan}}/{{tahun}}',
                'keterangan' => '',
            ],
        ];

        foreach ($jenisCutis as $cuti) {
            SimpegDaftarCuti::create($cuti);
        }
    }
}
