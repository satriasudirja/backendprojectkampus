<?php

namespace Database\Seeders;

use App\Models\MasterPotonganWajib;
use Illuminate\Database\Seeder;

class MasterPotonganWajibSeeder extends Seeder
{
    public function run(): void
    {
        $potonganList = [
            [
                'kode_potongan' => 'BPJS-KES',
                'nama_potongan' => 'BPJS Kesehatan',
                'jenis_potongan' => 'persen',
                'nilai_potongan' => 1.00, // 1% dari gaji pokok
                'dihitung_dari' => 'gaji_pokok',
                'is_active' => true,
                'keterangan' => 'Iuran BPJS Kesehatan ditanggung karyawan (1% dari gaji pokok)'
            ],
            [
                'kode_potongan' => 'BPJS-TK-JHT',
                'nama_potongan' => 'BPJS Ketenagakerjaan - Jaminan Hari Tua (JHT)',
                'jenis_potongan' => 'persen',
                'nilai_potongan' => 2.00, // 2% dari gaji pokok
                'dihitung_dari' => 'gaji_pokok',
                'is_active' => true,
                'keterangan' => 'Iuran JHT ditanggung karyawan (2% dari gaji pokok)'
            ],
            [
                'kode_potongan' => 'BPJS-TK-JP',
                'nama_potongan' => 'BPJS Ketenagakerjaan - Jaminan Pensiun (JP)',
                'jenis_potongan' => 'persen',
                'nilai_potongan' => 1.00, // 1% dari gaji pokok
                'dihitung_dari' => 'gaji_pokok',
                'is_active' => true,
                'keterangan' => 'Iuran JP ditanggung karyawan (1% dari gaji pokok)'
            ],
            [
                'kode_potongan' => 'PPh21',
                'nama_potongan' => 'Pajak Penghasilan Pasal 21',
                'jenis_potongan' => 'persen',
                'nilai_potongan' => 5.00, // Contoh 5%, sesuaikan dengan aturan yang berlaku
                'dihitung_dari' => 'penghasilan_bruto',
                'is_active' => true,
                'keterangan' => 'PPh 21 dihitung dari penghasilan bruto (simplified, seharusnya ada PTKP, dll)'
            ],
        ];

        foreach ($potonganList as $potongan) {
            MasterPotonganWajib::create($potongan);
        }
    }
}