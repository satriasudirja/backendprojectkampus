<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisPKMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_pkm
        $data = [
            ['kode' => 'P001', 'nama_pkm' => 'Menduduki jabatan pada lembaga pemerintah'],
            ['kode' => 'P002', 'nama_pkm' => 'Melaksanakan pengembangan hasil pendidikan dan penelitian'],
            ['kode' => 'P003', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Tingkat Internasional dalam satu semester / lebih)'],
            ['kode' => 'P004', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Tingkat Nasional dalam satu semester / lebih)'],
            ['kode' => 'P005', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Tingkat Lokal dalam satu semester / lebih)'],
            ['kode' => 'P006', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Internasional kurang 1 semester minimal 1 bulan)'],
            ['kode' => 'P007', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Nasional kurang 1 semester minimal 1 bulan)'],
            ['kode' => 'P008', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Lokal kurang 1 semester minimal 1 bulan)'],
            ['kode' => 'P009', 'nama_pkm' => 'Memberi latihan/ penyuluhan/ penataran/ ceramah (Insidential)'],
            ['kode' => 'P010', 'nama_pkm' => 'Memberi pelayanan kepada masyarakat atau kegiatan lain (Bidang keahlian)'],
            ['kode' => 'P011', 'nama_pkm' => 'Memberi pelayanan kepada masyarakat atau kegiatan lain (Penugasan Perguruan Tinggi)'],
            ['kode' => 'P012', 'nama_pkm' => 'Memberi pelayanan kepada masyarakat atau kegiatan lain (Fungsi jabatan)'],
            ['kode' => 'P013', 'nama_pkm' => 'Membuat/ menulis karya pengabdian yang tidak di publikasikan (Tiap karya)'],
        ];

        // Insert data ke tabel jenis_pkm
        DB::table('jenis_pkm')->insert($data);
    }
}