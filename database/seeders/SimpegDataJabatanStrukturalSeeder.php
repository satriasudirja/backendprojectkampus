<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataJabatanStrukturalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        
        // Dapatkan ID pegawai yang sudah ada di database
        $pegawaiIds = DB::table('simpeg_pegawai')->pluck('id')->toArray();
        
        // Jika tidak ada pegawai, jangan jalankan seeder
        if (empty($pegawaiIds)) {
            $this->command->info('Tidak ada data pegawai di tabel simpeg_pegawai. Seeder tidak dijalankan.');
            return;
        }
        
        // Dapatkan ID jabatan struktural yang sudah ada
        $jabatanIds = DB::table('simpeg_jabatan_struktural')->pluck('id')->toArray();
        
        // Jika tidak ada jabatan struktural, jangan jalankan seeder
        if (empty($jabatanIds)) {
            $this->command->info('Tidak ada data di tabel simpeg_jabatan_struktural. Seeder tidak dijalankan.');
            return;
        }
        
        // Gunakan pegawai dan jabatan yang ada
        $data = [];
        
        // Data untuk 4 pegawai (atau kurang jika tidak cukup data)
        $count = min(count($pegawaiIds), count($jabatanIds), 4);
        
        for ($i = 0; $i < $count; $i++) {
            $pegawaiId = $pegawaiIds[$i];
            $jabatanId = $jabatanIds[$i];
            
            $data[] = [
                'jabatan_struktural_id' => $jabatanId,
                'pegawai_id' => $pegawaiId,
                'tgl_mulai' => '2023-01-01',
                'tgl_selesai' => '2027-12-31',
                'no_sk' => 'SK/REKTOR/2023/' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'tgl_sk' => '2022-12-15',
                'pejabat_penetap' => 'Rektor',
                'file_jabatan' => 'sk_jabatan_2023_' . $i . '.pdf',
                'tgl_input' => $now->format('Y-m-d'),
                'status_pengajuan' => 'approved',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($data)) {
            DB::table('simpeg_data_jabatan_struktural')->insert($data);
            $this->command->info('Berhasil insert ' . count($data) . ' data jabatan struktural.');
        } else {
            $this->command->info('Tidak ada data yang diinsert.');
        }
    }
}