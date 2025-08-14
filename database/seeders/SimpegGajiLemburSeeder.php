<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
        // --- PERBAIKAN 1: Ambil UUID pegawai yang sebenarnya dari database ---
        // Bukan lagi array integer [1, 2, 3, ...].
        $pegawaiIds = DB::table('simpeg_pegawai')->pluck('id')->toArray();

        // Validasi: Hentikan seeder jika tidak ada data pegawai.
        if (empty($pegawaiIds)) {
            $this->command->error('Tabel pegawai kosong. Harap jalankan SimpegPegawaiSeeder terlebih dahulu.');
            return;
        }
        
        // Status yang mungkin
        $statuses = ['pending', 'approved', 'rejected', 'paid'];
        
        // Tanggal sekarang
        $now = Carbon::now();
        
        // Buat data dalam satu batch untuk efisiensi
        $dataLembur = [];

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
                    $status = $statuses[array_rand($statuses)];
                    
                    // --- PERBAIKAN 2: Buat UUID baru untuk SETIAP baris lembur ---
                    // dan kumpulkan data ke dalam array $dataLembur
                    $dataLembur[] = [
                        'id' => Str::uuid(), // UUID baru di setiap iterasi
                        'pegawai_id' => $pegawaiId, // Gunakan UUID pegawai
                        'tanggal' => $tanggal,
                        'jam_mulai' => $jamMulai,
                        'jam_selesai' => $jamSelesai,
                        'durasi' => $durasi,
                        'upah_perjam' => $upahPerjam,
                        'total_upah' => $totalUpah,
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        // --- PERBAIKAN 3: Insert semua data sekaligus di luar loop ---
        // Ini jauh lebih cepat dan efisien daripada insert satu per satu.
        DB::table('simpeg_gaji_lembur')->insert($dataLembur);
    }
}
