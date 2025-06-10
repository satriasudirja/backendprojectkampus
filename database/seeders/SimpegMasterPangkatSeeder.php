<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegMasterPangkat;

class SimpegMasterPangkatSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['pangkat' => 'I/a', 'nama_golongan' => 'Juru Muda', 'tunjangan' => 2100000],
            ['pangkat' => 'I/b', 'nama_golongan' => 'Juru Muda Tingkat I', 'tunjangan' => 2200000],
            ['pangkat' => 'I/c', 'nama_golongan' => 'Juru', 'tunjangan' => 2400000],
            ['pangkat' => 'I/d', 'nama_golongan' => 'Juru Tingkat I', 'tunjangan' => 2400000],
            ['pangkat' => 'II/a', 'nama_golongan' => 'Pengatur Muda', 'tunjangan' => 2400000],
            ['pangkat' => 'II/b', 'nama_golongan' => 'Pengatur Muda Tingkat I', 'tunjangan' => 2400000],
            ['pangkat' => 'II/c', 'nama_golongan' => 'Pengatur', 'tunjangan' => 2400000],
            ['pangkat' => 'II/d', 'nama_golongan' => 'Pengatur Tingkat I', 'tunjangan' => 2400000],
            ['pangkat' => 'III/a', 'nama_golongan' => 'Penata Muda', 'tunjangan' => 2400000],
            ['pangkat' => 'III/b', 'nama_golongan' => 'Penata Muda Tingkat I', 'tunjangan' => 2400000],
            ['pangkat' => 'III/c', 'nama_golongan' => 'Penata', 'tunjangan' => 2400000],
            ['pangkat' => 'III/d', 'nama_golongan' => 'Penata Tingkat I', 'tunjangan' => 2400000],
            ['pangkat' => 'IV/a', 'nama_golongan' => 'Pembina', 'tunjangan' => 2400000],
            ['pangkat' => 'IV/b', 'nama_golongan' => 'Pembina Tingkat I', 'tunjangan' => 2400000],
            ['pangkat' => 'IV/c', 'nama_golongan' => 'Pembina Utama Muda', 'tunjangan' => 2400000],
            ['pangkat' => 'IV/d', 'nama_golongan' => 'Pembina Utama Madya', 'tunjangan' => 2400000],
            ['pangkat' => 'IV/e', 'nama_golongan' => 'Pembina Utama', 'tunjangan' => 2400000],
        ];

        foreach ($data as $item) {
            SimpegMasterPangkat::create($item);
        }
    }
}