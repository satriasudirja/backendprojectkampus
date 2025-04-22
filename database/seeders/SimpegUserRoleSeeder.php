<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpegUserRoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'nama' => 'Admin',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'Dosen Praktisi/Industri',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'Tenaga Kependidikan',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'Dosen',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('simpeg_users_roles')->insert($roles);
    }
}