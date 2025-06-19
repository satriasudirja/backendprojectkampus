<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SimpegJabatanAkademikSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // Ambil ID role berdasarkan nama
        $roles = DB::table('simpeg_users_roles')
            ->pluck('id', 'nama'); // ['Admin' => 1, 'Dosen' => 2, ...]

        $data = [



            
           
            ['kode' => 'AA', 'jabatan_akademik' => 'Asisten Ahli', 'role' => 'Dosen'],
            ['kode' => 'DP', 'jabatan_akademik' => 'Dosen Praktisi/Industri', 'role' => 'Dosen Praktisi/Industri'],
            ['kode' => 'GB', 'jabatan_akademik' => 'Guru Besar', 'role' => 'Dosen'],
            ['kode' => 'GP', 'jabatan_akademik' => 'Guru Pamong', 'role' => 'Dosen Praktisi/Industri'],
            ['kode' => 'K1', 'jabatan_akademik' => 'Administrasi', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'K2', 'jabatan_akademik' => 'Keamanan', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'K3', 'jabatan_akademik' => 'Laboran', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'K5', 'jabatan_akademik' => 'Pustakawan', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'K6', 'jabatan_akademik' => 'Parkir', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'L',  'jabatan_akademik' => 'Lektor', 'role' => 'Dosen'],
            ['kode' => 'LK', 'jabatan_akademik' => 'Lektor Kepala', 'role' => 'Dosen'],
            ['kode' => 'P1', 'jabatan_akademik' => 'Dosen', 'role' => 'Dosen'],
            ['kode' => 'RT', 'jabatan_akademik' => 'Rumah Tangga', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'S',  'jabatan_akademik' => 'Sopir', 'role' => 'Tenaga Kependidikan'],
            ['kode' => 'TA', 'jabatan_akademik' => 'Tenaga Ahli', 'role' => 'Dosen Praktisi/Industri'],
            ['kode' => 'TP', 'jabatan_akademik' => 'Tenaga Pengajar', 'role' => 'Dosen'],
        ];

        // Mapping ke dalam format insert
        $jabatanAkademik = array_map(function ($item) use ($roles, $now) {
            return [
                'kode' => $item['kode'],
                'jabatan_akademik' => $item['jabatan_akademik'],
                'role_id' => $roles[$item['role']] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $data);

        DB::table('simpeg_jabatan_akademik')->insert($jabatanAkademik);
    }
}
