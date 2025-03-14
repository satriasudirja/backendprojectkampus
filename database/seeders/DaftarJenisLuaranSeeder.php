<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DaftarJenisLuaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel daftar_jenis_luaran
        $data = [
            ['kode' => '5', 'jenis_luaran' => 'Buku'],
            ['kode' => '4', 'jenis_luaran' => 'HKI'],
            ['kode' => '2', 'jenis_luaran' => 'Jenis Luaran Lainnya'],
            ['kode' => '6', 'jenis_luaran' => 'Pembicara'],
            ['kode' => '1', 'jenis_luaran' => 'Produk Teknologi Tepat Guna'],
            ['kode' => '3', 'jenis_luaran' => 'Publikasi'],
            ['kode' => '7', 'jenis_luaran' => 'Visiting Scientist'],
        ];

        // Insert data ke tabel daftar_jenis_luaran
        DB::table('daftar_jenis_luaran')->insert($data);
    }
}