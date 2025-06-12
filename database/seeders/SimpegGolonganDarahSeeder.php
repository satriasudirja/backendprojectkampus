<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SimpegGolonganDarah;

class SimpegGolonganDarahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $golonganDarah = [
            ['golongan_darah' => 'A'],
            ['golongan_darah' => 'B'],
            ['golongan_darah' => 'AB'],
            ['golongan_darah' => 'O'],
        ];

        foreach ($golonganDarah as $data) {
            SimpegGolonganDarah::create($data);
        }
    }
}