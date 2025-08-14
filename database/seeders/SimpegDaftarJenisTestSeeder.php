<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // PERBAIKAN: Tambahkan Str facade

class SimpegDaftarJenisTestSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $jenisTestData = [
            ['kode' => 10, 'jenis_tes' => 'Tes Potensi Akademik', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 100.00],
            ['kode' => 11, 'jenis_tes' => 'IELTS', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 9.00],
            ['kode' => 12, 'jenis_tes' => 'TOEFL iBT', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 120.00],
            ['kode' => 13, 'jenis_tes' => 'TOEFL ITP', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 677.00],
            ['kode' => 15, 'jenis_tes' => 'TOEP-TEFLIN', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 100.00],
            ['kode' => 29, 'jenis_tes' => 'TOEFL CBT', 'nilai_minimal' => 0.00, 'nilai_maksimal' => 300.00],
        ];

        // PERBAIKAN: Loop melalui data untuk menambahkan id dan timestamps
        $dataToInsert = array_map(function ($item) use ($now) {
            return array_merge($item, [
                'id' => Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $jenisTestData);
        
        DB::table('simpeg_daftar_jenis_test')->insert($dataToInsert);
    }
}
