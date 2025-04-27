<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegDaftarJenisLuaran;

class SimpegDaftarJenisLuaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['kode' => '5', 'jenis_luaran' => 'Buku'],
            ['kode' => '4', 'jenis_luaran' => 'HKI'],
            ['kode' => '2', 'jenis_luaran' => 'Jenis Luaran Lainnya'],
            ['kode' => '6', 'jenis_luaran' => 'Pembicara'],
            ['kode' => '1', 'jenis_luaran' => 'Produk Teknologi Tepat Guna'],
            ['kode' => '3', 'jenis_luaran' => 'Publikasi'],
            ['kode' => '7', 'jenis_luaran' => 'Visiting Scientist'],
        ];

        foreach ($data as $item) {
            SimpegDaftarJenisLuaran::create($item);
        }
    }
}
