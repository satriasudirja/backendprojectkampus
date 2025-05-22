<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegGajiLembur;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpegGajiLemburSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate table terlebih dahulu (opsional)
        // DB::table('simpeg_gaji_lembur')->truncate();
        
        // Data sample pegawai IDs (asumsikan sudah ada di tabel simpeg_pegawai)
        $pegawaiIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        
        // Status yang mungkin
        $statuses = ['pending', 'approved', 'rejected', 'paid'];
        
        // Tanggal sekarang
        $now = Carbon::now();
        
        // Generate data lembur untuk 3 bulan terakhir
        for ($i = 0; $i < 3; $i++) {
            $month = $now->copy()->subMonths($i);
            
            // Untuk setiap pegawai, buat beberapa record lembur
            foreach ($pegawaiIds as $pegawaiId) {
                // Jumlah record lembur acak per pegawai per bulan
                $lemburCount = rand(0, 5);  // 0-5 lembur per bulan
                
                for ($j = 0; $j < $lemburCount; $j++) {
                    // Tanggal acak dalam bulan tersebut
                    $day = rand(1, $month->daysInMonth);
                    $tanggal = Carbon::createFromDate($month->year, $month->month, $day);
                    
                    // Jam mulai acak (antara 17:00 - 19:00)
                    $jamMulai = sprintf('%02d:%02d', rand(17, 19), rand(0, 59));
                    
                    // Durasi acak (1-5 jam)
                    $durasi = rand(1, 5);
                    
                    // Hitung jam selesai
                    $jamMulaiObj = Carbon::createFromFormat('H:i', $jamMulai);
                    $jamSelesai = $jamMulaiObj->copy()->addHours($durasi)->format('H:i');
                    
                    // Upah per jam acak (50-100 ribu)
                    $upahPerjam = rand(50, 100) * 1000;
                    
                    // Total upah = upah/jam * durasi
                    $totalUpah = $upahPerjam * $durasi;
                    
                    // Status acak
                    // Jika bulan terbaru, lebih banyak status pending
                    if ($i == 0) {
                        $statusIndex = rand(0, min(1, count($statuses) - 1));  // Lebih banyak pending dan approved
                    }
                    // Jika bulan menengah, lebih banyak approved
                    else if ($i == 1) {
                        $statusIndex = rand(min(1, count($statuses) - 1), min(2, count($statuses) - 1)); // Lebih banyak approved
                    }
                    // Jika bulan lama, lebih banyak paid
                    else {
                        $statusIndex = rand(min(2, count($statuses) - 1), count($statuses) - 1); // Lebih banyak approved dan paid
                    }
                    
                    $status = $statuses[$statusIndex];
                    
                    // Insert ke database menggunakan Query Builder
                    DB::table('simpeg_gaji_lembur')->insert([
                        'pegawai_id' => $pegawaiId,
                        'tanggal' => $tanggal,
                        'jam_mulai' => $jamMulai,
                        'jam_selesai' => $jamSelesai,
                        'durasi' => $durasi,
                        'upah_perjam' => $upahPerjam,
                        'total_upah' => $totalUpah,
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}