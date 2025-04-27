<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpegDaftarJenisTestSeeder extends Seeder
{
    public function run()
    {
        DB::table('simpeg_daftar_jenis_test')->insert([
            ['kode' => 10, 'jenis_tes' => 'Tes Potensi Akademik', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 100.00],
            ['kode' => 11, 'jenis_tes' => 'IELTS', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 9.00],
            ['kode' => 12, 'jenis_tes' => 'TOEFL iBT', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 120.00],
            ['kode' => 13, 'jenis_tes' => 'TOEFL ITP', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 677.00],
            ['kode' => 15, 'jenis_tes' => 'TOEP-TEFLIN', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 100.00],
            ['kode' => 29, 'jenis_tes' => 'TOEFL CBT', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 300.00],
        ]);
    }
}
