<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SimpegJabatanAkademikSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // Ambil ID role berdasarkan nama
        $roles = DB::table('simpeg_users_roles')
            ->pluck('id', 'nama'); // ['Admin' => 1, 'Dosen' => 2, ...]

        $data = [



            
           
            ['kode' => 'AA', 'jabatan_akademik' => 'Asisten Ahli'],
            ['kode' => 'DP', 'jabatan_akademik' => 'Dosen Praktisi/Industri'],
            ['kode' => 'GB', 'jabatan_akademik' => 'Guru Besar'],
            ['kode' => 'GP', 'jabatan_akademik' => 'Guru Pamong'],
            ['kode' => 'K1', 'jabatan_akademik' => 'Administrasi'],
            ['kode' => 'K2', 'jabatan_akademik' => 'Keamanan'],
            ['kode' => 'K3', 'jabatan_akademik' => 'Laboran'],
            ['kode' => 'K5', 'jabatan_akademik' => 'Pustakawan'],
            ['kode' => 'K6', 'jabatan_akademik' => 'Parkir'],
            ['kode' => 'L',  'jabatan_akademik' => 'Lektor'],
            ['kode' => 'LK', 'jabatan_akademik' => 'Lektor Kepala'],
            ['kode' => 'P1', 'jabatan_akademik' => 'Dosen'],
            ['kode' => 'RT', 'jabatan_akademik' => 'Rumah Tangga'],
            ['kode' => 'S',  'jabatan_akademik' => 'Sopir'],
            ['kode' => 'TA', 'jabatan_akademik' => 'Tenaga Ahli'],
            ['kode' => 'TP', 'jabatan_akademik' => 'Tenaga Pengajar'],
        ];

        // Mapping ke dalam format insert
        $jabatanAkademik = array_map(function ($item) use ($roles, $now) {
            return [
                'id' => Str::uuid(),
                'kode' => $item['kode'],
                'jabatan_akademik' => $item['jabatan_akademik'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $data);

        DB::table('simpeg_jabatan_akademik')->insert($jabatanAkademik);
    }
}
