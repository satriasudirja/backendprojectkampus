<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterOutputPenelitian;

class SimpegMasterOutputPenelitianSeeder extends Seeder
{
    public function run()
    {
        $outputs = [
            ['kode' => 'O001', 'output_penelitian' => 'Buku'],
            ['kode' => 'O002', 'output_penelitian' => 'Monograf'],
            ['kode' => 'O003', 'output_penelitian' => 'Jurnal Internasional'],
            ['kode' => 'O004', 'output_penelitian' => 'Jurnal Nasional Terakreditasi'],
            ['kode' => 'O005', 'output_penelitian' => 'Jurnal Nasional Tidak Terakreditasi'],
            ['kode' => 'O006', 'output_penelitian' => 'Seminar Internasional'],
            ['kode' => 'O007', 'output_penelitian' => 'Seminar Nasional'],
            ['kode' => 'O008', 'output_penelitian' => 'Poster Internasional'],
            ['kode' => 'O009', 'output_penelitian' => 'Poster Nasional'],
            ['kode' => 'O010', 'output_penelitian' => 'Koran/ Majalah Umum'],
            ['kode' => 'O011', 'output_penelitian' => 'Hasil Pemikiran yang Tidak Dipublikasikan (tersimpan dalam perpustakaan PT)'],
            ['kode' => 'O014', 'output_penelitian' => 'Membuat Rencana dan Karya Teknologi yang Dipatenkan Nasional'],
            ['kode' => 'O015', 'output_penelitian' => 'Membuat Rencana dan Karya Teknologi yang Dipatenkan Internasional'],
            ['kode' => 'O016', 'output_penelitian' => 'Membuat Rancangan dan Karya Teknologi, Rancangan dan Karya Seni Monumental/pertunjukan - Lokal'],
            ['kode' => 'O017', 'output_penelitian' => 'Membuat Rancangan dan Karya Teknologi, Rancangan dan Karya Seni Monumental/pertunjukan - Nasional'],
            ['kode' => 'O018', 'output_penelitian' => 'Membuat Rancangan dan Karya Teknologi, Rancangan dan Karya Seni Monumental/pertunjukan - Int'],
        ];

        foreach ($outputs as $output) {
            MasterOutputPenelitian::create($output);
        }
    }
}