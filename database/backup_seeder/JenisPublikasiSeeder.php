<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisPublikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_publikasi
        $data = [
            ['kode' => '11', 'jenis_publikasi' => 'Monograf', 'bobot' => 0.4],
            ['kode' => '12', 'jenis_publikasi' => 'Buku referensi', 'bobot' => 0.4],
            ['kode' => '13', 'jenis_publikasi' => 'Buku lainnya', 'bobot' => 0.4],
            ['kode' => '14', 'jenis_publikasi' => 'Book chapter nasional', 'bobot' => 0.4],
            ['kode' => '15', 'jenis_publikasi' => 'Book chapter internasional', 'bobot' => 0.6],
            ['kode' => '21', 'jenis_publikasi' => 'Jurnal nasional', 'bobot' => 0.4],
            ['kode' => '22', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'bobot' => 0.4],
            ['kode' => '23', 'jenis_publikasi' => 'Jurnal internasional', 'bobot' => 0.4],
            ['kode' => '24', 'jenis_publikasi' => 'Jurnal internasional bereputasi', 'bobot' => 0.8],
            ['kode' => '25', 'jenis_publikasi' => 'Artikel ilmiah', 'bobot' => null], // Jika bobot tidak ada, isi dengan null
        ];

        // Insert data ke tabel jenis_publikasi
        DB::table('jenis_publikasi')->insert($data);
    }
}