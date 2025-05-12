<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJenisPublikasi;

class SimpegJenisPublikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            ['kode' => '11', 'jenis_publikasi' => 'Monograf'],
            ['kode' => '12', 'jenis_publikasi' => 'Buku referensi'],
            ['kode' => '13', 'jenis_publikasi' => 'Buku lainnya'],
            ['kode' => '14', 'jenis_publikasi' => 'Book chapter nasional'],
            ['kode' => '15', 'jenis_publikasi' => 'Book chapter internasional'],
            ['kode' => '21', 'jenis_publikasi' => 'Jurnal nasional'],
            ['kode' => '22', 'jenis_publikasi' => 'Jurnal nasional terakreditasi'],
            ['kode' => '23', 'jenis_publikasi' => 'Jurnal internasional'],
            ['kode' => '24', 'jenis_publikasi' => 'Jurnal internasional bereputasi'],
            ['kode' => '25', 'jenis_publikasi' => 'Artikel ilmiah'],
        ];

        foreach ($data as $item) {
            SimpegJenisPublikasi::create($item);
        }
    }
}