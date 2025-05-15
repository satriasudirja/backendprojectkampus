<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegJabatanStrukturalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        
        $data = [
            [
                'unit_kerja_id' => 1, // Assuming unit kerja with ID 1 exists
                'jenis_jabatan_struktural_id' => 3, // Eselon II.a
                'pangkat_id' => 1, // Assuming pangkat with ID 1 exists
                'eselon_id' => 1, // Assuming eselon with ID 1 exists
                'kode' => 'RKT01',
                'singkatan' => 'Rektor',
                'alamat_email' => 'rektor@universitas.ac.id',
                'beban_sks' => 2,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => null,
                'parent_jabatan' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'unit_kerja_id' => 2, // Assuming unit kerja with ID 2 exists
                'jenis_jabatan_struktural_id' => 5, // Eselon III.a
                'pangkat_id' => 2, // Assuming pangkat with ID 2 exists
                'eselon_id' => 3, // Assuming eselon with ID 3 exists
                'kode' => 'DKN01',
                'singkatan' => 'Dekan',
                'alamat_email' => 'dekan@fak.universitas.ac.id',
                'beban_sks' => 4,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => null,
                'parent_jabatan' => 'RKT01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'unit_kerja_id' => 3, // Assuming unit kerja with ID 3 exists
                'jenis_jabatan_struktural_id' => 6, // Eselon III.b
                'pangkat_id' => 3, // Assuming pangkat with ID 3 exists
                'eselon_id' => 4, // Assuming eselon with ID 4 exists
                'kode' => 'KJR01',
                'singkatan' => 'Kajur',
                'alamat_email' => 'kajur@dep.universitas.ac.id',
                'beban_sks' => 6,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => 'Kepala Jurusan',
                'parent_jabatan' => 'DKN01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'unit_kerja_id' => 4, // Assuming unit kerja with ID 4 exists
                'jenis_jabatan_struktural_id' => 7, // Eselon IV.a
                'pangkat_id' => 4, // Assuming pangkat with ID 4 exists
                'eselon_id' => 5, // Assuming eselon with ID 5 exists
                'kode' => 'KPS01',
                'singkatan' => 'Kaprodi',
                'alamat_email' => 'kaprodi@prodi.universitas.ac.id',
                'beban_sks' => 6,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => 'Kepala Program Studi',
                'parent_jabatan' => 'KJR01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('simpeg_jabatan_struktural')->insert($data);
    }
}