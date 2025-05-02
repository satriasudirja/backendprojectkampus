<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegMasterPangkat;

class SimpegMasterPangkatSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['pangkat' => 'I/a', 'nama_golongan' => 'Juru Muda'],
            ['pangkat' => 'I/b', 'nama_golongan' => 'Juru Muda Tingkat I'],
            ['pangkat' => 'I/c', 'nama_golongan' => 'Juru'],
            ['pangkat' => 'I/d', 'nama_golongan' => 'Juru Tingkat I'],
            ['pangkat' => 'II/a', 'nama_golongan' => 'Pengatur Muda'],
            ['pangkat' => 'II/b', 'nama_golongan' => 'Pengatur Muda Tingkat I'],
            ['pangkat' => 'II/c', 'nama_golongan' => 'Pengatur'],
            ['pangkat' => 'II/d', 'nama_golongan' => 'Pengatur Tingkat I'],
            ['pangkat' => 'III/a', 'nama_golongan' => 'Penata Muda'],
            ['pangkat' => 'III/b', 'nama_golongan' => 'Penata Muda Tingkat I'],
            ['pangkat' => 'III/c', 'nama_golongan' => 'Penata'],
            ['pangkat' => 'III/d', 'nama_golongan' => 'Penata Tingkat I'],
            ['pangkat' => 'IV/a', 'nama_golongan' => 'Pembina'],
            ['pangkat' => 'IV/b', 'nama_golongan' => 'Pembina Tingkat I'],
            ['pangkat' => 'IV/c', 'nama_golongan' => 'Pembina Utama Muda'],
            ['pangkat' => 'IV/d', 'nama_golongan' => 'Pembina Utama Madya'],
            ['pangkat' => 'IV/e', 'nama_golongan' => 'Pembina Utama'],
        ];

        foreach ($data as $item) {
            SimpegMasterPangkat::create($item);
        }
    }
}