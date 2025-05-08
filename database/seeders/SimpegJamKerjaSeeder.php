<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegJamKerja;

class SimpegJamKerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'jenis_jam_kerja' => 'Jam Kerja Normal',
                'jam_normal' => true,
                'jam_datang' => '08:15',
                'jam_pulang' => '16:00',
            ]
        ];

        foreach ($data as $item) {
            SimpegJamKerja::create($item);
        }
    }
}