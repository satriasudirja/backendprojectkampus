<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
                'id' => Str::uuid(),
                'nama' => 'Dosen',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid(),
                'nama' => 'Dosen LB',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid(),
                'nama' => 'Tenaga Kependidikan',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
