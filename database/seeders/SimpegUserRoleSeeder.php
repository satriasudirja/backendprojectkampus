<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegUserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('simpeg_users_roles')->insert([
            [
                'nama' => 'Admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nama' => 'Dosen',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nama' => 'Dosen Praktisi/Industri',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nama' => 'Tenaga Kependidikan',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
