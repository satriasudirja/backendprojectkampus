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
                'nama' => 'admin',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'dosen',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'tenaga_kependidikan',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'dosen_industri',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('simpeg_users_roles')->insert($roles);
    }
}