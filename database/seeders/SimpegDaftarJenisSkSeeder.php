<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpegDaftarJenisSkSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $jenisSkData = [
            ['kode' => 'PK', 'jenis_sk' => 'Perjanjian Kontrak'],
            ['kode' => 'SK0', 'jenis_sk' => 'SK Tetap 80%'],
            ['kode' => 'SK1', 'jenis_sk' => 'SK Tetap 100%'],
            ['kode' => 'SK2', 'jenis_sk' => 'SK Inpassing'],
            ['kode' => 'SK3', 'jenis_sk' => 'SK Pangkat'],
            ['kode' => 'SK4', 'jenis_sk' => 'SK Berkala YPIKA'],
            ['kode' => 'SK5', 'jenis_sk' => 'SK Pangkat YPIKA'],
        ];

        // PERBAIKAN: Loop melalui data untuk menambahkan id dan timestamps
        $dataToInsert = array_map(function ($item) use ($now) {
            return array_merge($item, [
                'id' => Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $jenisSkData);

        DB::table('simpeg_daftar_jenis_sk')->insert($dataToInsert);
    }
}
