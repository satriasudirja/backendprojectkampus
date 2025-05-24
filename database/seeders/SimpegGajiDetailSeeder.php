<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegGajiDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimpegGajiDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate table terlebih dahulu (opsional)
        // DB::table('simpeg_gaji_detail')->truncate();
        
        // Dapatkan semua slip gaji yang sudah ada
        $slips = DB::table('simpeg_gaji_slip')->get();
        
        // Dapatkan komponen gaji berdasarkan jenis
        $komponenTunjangan = DB::table('simpeg_gaji_komponen')->where('jenis', 'tunjangan')->get();
        $komponenPotongan = DB::table('simpeg_gaji_komponen')->where('jenis', 'potongan')->get();
        
        foreach ($slips as $slip) {
            // Menghitung total tunjangan 
            $totalTunjangan = $slip->total_pendapatan;
            $remainingTunjangan = $totalTunjangan;
            
            // Menambahkan detail tunjangan
            foreach ($komponenTunjangan as $index => $komponen) {
                // Jika ini adalah komponen terakhir, gunakan sisa nilai untuk balance
                if ($index == count($komponenTunjangan) - 1) {
                    $jumlah = $remainingTunjangan;
                } else {
                    // Untuk komponen lain, ambil persentase acak dari total
                    // Pastikan masih menyisakan setidaknya 10% untuk komponen lainnya
                    $maxPercentage = min(70, 100 - (10 * (count($komponenTunjangan) - $index - 1)));
                    $percentage = rand(10, $maxPercentage);
                    $jumlah = round(($totalTunjangan * $percentage) / 100);
                    $remainingTunjangan -= $jumlah;
                }
                
                // Buat detail tunjangan menggunakan Query Builder
                DB::table('simpeg_gaji_detail')->insert([
                    'gaji_slip_id' => $slip->id,
                    'komponen_id' => $komponen->id,
                    'jumlah' => $jumlah,
                    'keterangan' => "Komponen {$komponen->nama_komponen} periode " . date('F Y'),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // Menghitung total potongan
            $totalPotongan = $slip->total_potongan;
            $remainingPotongan = $totalPotongan;
            
            // Menambahkan detail potongan
            foreach ($komponenPotongan as $index => $komponen) {
                // Jika ini adalah komponen terakhir, gunakan sisa nilai
                if ($index == count($komponenPotongan) - 1) {
                    $jumlah = $remainingPotongan;
                } else {
                    // Untuk komponen lain, ambil persentase acak dari total
                    $maxPercentage = min(70, 100 - (10 * (count($komponenPotongan) - $index - 1)));
                    $percentage = rand(10, $maxPercentage);
                    $jumlah = round(($totalPotongan * $percentage) / 100);
                    $remainingPotongan -= $jumlah;
                }
                
                // Buat detail potongan menggunakan Query Builder
                DB::table('simpeg_gaji_detail')->insert([
                    'gaji_slip_id' => $slip->id,
                    'komponen_id' => $komponen->id,
                    'jumlah' => $jumlah,
                    'keterangan' => "Potongan {$komponen->nama_komponen} periode " . date('F Y'),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}