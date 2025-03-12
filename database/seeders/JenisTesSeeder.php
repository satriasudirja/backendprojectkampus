<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisTesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel jenis_tes
        $data = [
            ['kode' => '10', 'jenis_tes' => 'Tes Potensi Akademik', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 100.00],
            ['kode' => '11', 'jenis_tes' => 'IELTS', 'nilai_minimal' => null, 'nilai_maksimal' => 9.00],
            ['kode' => '12', 'jenis_tes' => 'TOEFL iBT', 'nilai_minimal' => null, 'nilai_maksimal' => 120.00],
            ['kode' => '13', 'jenis_tes' => 'TOEFL ITP', 'nilai_minimal' => null, 'nilai_maksimal' => 677.00],
            ['kode' => '15', 'jenis_tes' => 'TOEP-TEFLIN', 'nilai_minimal' => null, 'nilai_maksimal' => 100.00],
            ['kode' => '29', 'jenis_tes' => 'TOEFL CBT', 'nilai_minimal' => null, 'nilai_maksimal' => 300.00],
        ];

        // Insert data ke tabel jenis_tes
        DB::table('jenis_tes')->insert($data);
    }
}