<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\SimpegUser;
use App\Models\SimpegUserRole;

class SimpegUserSeeder extends Seeder
{
    public function run()
    {
        // Nonaktifkan foreign key check sementara
        DB::statement('TRUNCATE TABLE simpeg_users RESTART IDENTITY CASCADE;');

        $users = [
            // Admin
            [
                'role_id' => SimpegUserRole::ADMIN,
                'username' => '200011',
                'password' => Hash::make('Admin123!'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            
            // Dosen
            [
                'role_id' => SimpegUserRole::DOSEN,
                'username' => '200012',
                'password' => Hash::make('Dosen123!'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            
            // Tenaga Kependidikan
            [
                'role_id' => SimpegUserRole::TENAGA_KEPENDIDIKAN,
                'username' => '200013',
                'password' => Hash::make('Staff123!'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            
        
        ];

        // Insert data user
        DB::table('simpeg_users')->insert($users);

        // Tambahkan 10 user dummy dengan role random
    }
}