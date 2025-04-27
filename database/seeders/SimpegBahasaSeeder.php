<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegBahasa;

class SimpegBahasaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bahasas = [
            ['kode' => 'AR', 'nama_bahasa' => 'Bahasa Arab'],
            ['kode' => 'BN', 'nama_bahasa' => 'Bahasa Bengali'],
            ['kode' => 'DE', 'nama_bahasa' => 'Bahasa Jerman'],
            ['kode' => 'EL', 'nama_bahasa' => 'Bahasa Yunani'],
            ['kode' => 'EN', 'nama_bahasa' => 'Bahasa Inggris'],
            ['kode' => 'ES', 'nama_bahasa' => 'Bahasa Spanyol'],
            ['kode' => 'FA', 'nama_bahasa' => 'Bahasa Persia'],
            ['kode' => 'FR', 'nama_bahasa' => 'Bahasa Prancis'],
            ['kode' => 'HI', 'nama_bahasa' => 'Bahasa Hindi'],
            ['kode' => 'ID', 'nama_bahasa' => 'Bahasa Indonesia'],
            ['kode' => 'JA', 'nama_bahasa' => 'Bahasa Jepang'],
            ['kode' => 'JV', 'nama_bahasa' => 'Bahasa Jawa'],
            ['kode' => 'KO', 'nama_bahasa' => 'Bahasa Korea'],
            ['kode' => 'MS', 'nama_bahasa' => 'Bahasa Melayu'],
            ['kode' => 'NL', 'nama_bahasa' => 'Bahasa Belanda'],
            ['kode' => 'PT', 'nama_bahasa' => 'Bahasa Portugis'],
            ['kode' => 'RU', 'nama_bahasa' => 'Bahasa Rusia'],
            ['kode' => 'TH', 'nama_bahasa' => 'Bahasa Thailand'],
            ['kode' => 'ZH', 'nama_bahasa' => 'Bahasa Mandarin'],
        ];

        foreach ($bahasas as $bahasa) {
            SimpegBahasa::create($bahasa);
        }
    }
}
