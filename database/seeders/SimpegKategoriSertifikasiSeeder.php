<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SimpegKategoriSertifikasi;

class SimpegKategoriSertifikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kategoriSertifikasi = [
            'Kompetensi Profesional',
            'Kompetensi Pedagogik',
            'Kompetensi Kepribadian',
            'Kompetensi Sosial',
            'Teknis Khusus',
            'Bahasa Asing',
            'Pengembangan Diri',
            'Manajerial',
            'Digital Skill',
            'Kepemimpinan'
        ];

        foreach ($kategoriSertifikasi as $kategori) {
            SimpegKategoriSertifikasi::create([
                'kategori_sertifikasi' => $kategori
            ]);
        }
    }
}