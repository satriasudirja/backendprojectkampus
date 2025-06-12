<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisJabatanStruktural;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegJenisJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        $data = [
            ['kode' => '10000', 'jenis_jabatan_struktural' => 'Rektor', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11000', 'jenis_jabatan_struktural' => 'Wakil Rektor I', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11001', 'jenis_jabatan_struktural' => 'Wakil Rektor II', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11100', 'jenis_jabatan_struktural' => 'Wakil Rektor III', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11101', 'jenis_jabatan_struktural' => 'Wakil Rektor IV', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11200', 'jenis_jabatan_struktural' => 'Direktur Pascasarjana', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11300', 'jenis_jabatan_struktural' => 'Kepala Lembaga', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '11400', 'jenis_jabatan_struktural' => 'Ketua Unit', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12000', 'jenis_jabatan_struktural' => 'Dekan', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12100', 'jenis_jabatan_struktural' => 'Ketua Lembaga', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12110', 'jenis_jabatan_struktural' => 'Ketua Program Studi', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12111', 'jenis_jabatan_struktural' => 'Sekretaris Program Studi', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12112', 'jenis_jabatan_struktural' => 'Wakil Dekan I', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12120', 'jenis_jabatan_struktural' => 'Wakil Dekan II', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12130', 'jenis_jabatan_struktural' => 'Wakil Dekan III', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '12160', 'jenis_jabatan_struktural' => 'Kepala Laboratorium', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '20000', 'jenis_jabatan_struktural' => 'Staff / Karyawan', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '21000', 'jenis_jabatan_struktural' => 'Sekretaris', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '21009', 'jenis_jabatan_struktural' => 'Kepala Bidang', 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '21010', 'jenis_jabatan_struktural' => 'Kepala Bagian', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('simpeg_jenis_jabatan_struktural')->insert($data);
    }
}