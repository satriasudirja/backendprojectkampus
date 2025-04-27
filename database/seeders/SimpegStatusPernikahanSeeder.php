<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegStatusPernikahanSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $data = [
            [
                'kode_status' => 'D',
                'nama_status' => 'Duda/Janda',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kode_status' => 'M',
                'nama_status' => 'Menikah',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kode_status' => 'S',
                'nama_status' => 'Belum Pernah Menikah',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('simpeg_status_pernikahan')->insert($data);
    }
}
