<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegGajiSlip;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpegGajiSlipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate table terlebih dahulu (opsional)
        // DB::table('simpeg_gaji_slip')->truncate();
        
        // Dapatkan semua periode gaji yang sudah ada
        $periodes = DB::table('simpeg_gaji_periode')->get();
        
        // Data sample pegawai IDs (asumsikan sudah ada di tabel simpeg_pegawai)
        $pegawaiIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        
        foreach ($periodes as $periode) {
            // Tentukan status berdasarkan status periode
            $slipStatus = 'draft';
            $tglProses = null;
            
            if ($periode->status == 'proses') {
                $slipStatus = 'processed';
                $tglProses = Carbon::now()->subDays(rand(1, 10));
            } elseif ($periode->status == 'selesai') {
                // Untuk periode yang sudah selesai, buat status acak (processed, approved, atau paid)
                $statuses = ['processed', 'approved', 'paid'];
                $slipStatus = $statuses[array_rand($statuses)];
                $tglProses = Carbon::parse($periode->tgl_mulai)->addDays(rand(5, 15));
            }
            
            // Buat slip gaji untuk setiap pegawai
            foreach ($pegawaiIds as $pegawaiId) {
                // Hitung nilai tunjangan dan potongan secara acak
                $totalPendapatan = rand(3000000, 10000000);
                $totalPotongan = $totalPendapatan * (rand(5, 15) / 100); // 5-15% dari total pendapatan
                $gajiBersih = $totalPendapatan - $totalPotongan;
                
                // Insert ke database menggunakan Query Builder
                DB::table('simpeg_gaji_slip')->insert([
                    'pegawai_id' => $pegawaiId,
                    'periode_id' => $periode->id,
                    'total_pendapatan' => $totalPendapatan,
                    'total_potongan' => $totalPotongan,
                    'gaji_bersih' => $gajiBersih,
                    'status' => $slipStatus,
                    'tgl_proses' => $tglProses,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}