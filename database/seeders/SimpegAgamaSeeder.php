<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SimpegAgama;

class SimpegAgamaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agamas = [
            ['kode' => 1, 'nama_agama' => 'Islam'],
            ['kode' => 2, 'nama_agama' => 'Kristen Protestan'],
            ['kode' => 3, 'nama_agama' => 'Kristen Katolik'],
            ['kode' => 4, 'nama_agama' => 'Hindu'],
            ['kode' => 5, 'nama_agama' => 'Budha'],
            ['kode' => 6, 'nama_agama' => 'Konghucu'],
            ['kode' => 7, 'nama_agama' => 'Lain-lain'],
        ];

        foreach ($agamas as $agama) {
            SimpegAgama::create($agama);
        }
    }
}