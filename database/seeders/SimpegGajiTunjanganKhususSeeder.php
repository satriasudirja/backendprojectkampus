<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegGajiTunjanganKhusus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpegGajiTunjanganKhususSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate table terlebih dahulu (opsional)
        // DB::table('simpeg_gaji_tunjangan_khusus')->truncate();
        
        // Data sample pegawai IDs (asumsikan sudah ada di tabel simpeg_pegawai)
        $pegawaiIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        
        // Dapatkan komponen tunjangan untuk digunakan
        $komponenTunjangan = DB::table('simpeg_gaji_komponen')
            ->where('jenis', 'tunjangan')
            ->orWhere('jenis', 'benefit')
            ->get();
            
        if ($komponenTunjangan->isEmpty()) {
            // Jika komponen tunjangan tidak ditemukan, skip seeder ini
            $this->command->info('Skipping SimpegGajiTunjanganKhususSeeder: No tunjangan components found');
            return;
        }
        
        // Keterangan tunjangan khusus
        $keteranganList = [
            'Tunjangan khusus jabatan',
            'Tunjangan proyek khusus',
            'Tunjangan prestasi',
            'Tunjangan pendidikan',
            'Tunjangan keahlian khusus',
        ];
        
        // Tanggal saat ini
        $now = Carbon::now();
        
        // Jumlah total tunjangan khusus yang akan dibuat
        $totalTunjangan = 20;
        
        for ($i = 0; $i < $totalTunjangan; $i++) {
            // Pilih pegawai secara acak
            $pegawaiId = $pegawaiIds[array_rand($pegawaiIds)];
            
            // Pilih komponen secara acak
            $komponen = $komponenTunjangan->random();
            
            // Tentukan tanggal mulai (paling lama 1 tahun yang lalu)
            $tglMulai = $now->copy()->subDays(rand(0, 365));
            
            // Tentukan tanggal selesai (antara tanggal mulai sampai 1 tahun ke depan, atau null)
            $tglSelesai = null;
            
            // 70% tunjangan memiliki tanggal selesai
            if (rand(1, 10) <= 7) {
                $tglSelesai = $tglMulai->copy()->addDays(rand(30, 365));
            }
            
            // Jumlah tunjangan khusus (antara 500,000 - 3,000,000)
            $jumlah = rand(500, 3000) * 1000;
            
            // Pilih keterangan secara acak
            $keterangan = $keteranganList[array_rand($keteranganList)];
            
            // Insert ke database menggunakan Query Builder
            DB::table('simpeg_gaji_tunjangan_khusus')->insert([
                'pegawai_id' => $pegawaiId,
                'komponen_id' => $komponen->id,
                'jumlah' => $jumlah,
                'tgl_mulai' => $tglMulai,
                'tgl_selesai' => $tglSelesai,
                'keterangan' => $keterangan,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}