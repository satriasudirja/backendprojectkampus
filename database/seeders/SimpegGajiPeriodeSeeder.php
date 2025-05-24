<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegGajiPeriode;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpegGajiPeriodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate table terlebih dahulu (opsional)
        // DB::table('simpeg_gaji_periode')->truncate();

        // Tahun sekarang
        $currentYear = date('Y');
        $currentMonth = date('m');
        
        // Periode 6 bulan terakhir
        for ($i = 5; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            $year = $currentYear;
            
            // Handle jika bulan menjadi negatif (tahun sebelumnya)
            if ($month <= 0) {
                $month += 12;
                $year -= 1;
            }
            
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            
            // Format nama bulan dalam Bahasa Indonesia
            $indonesianMonths = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];
            
            $monthName = $indonesianMonths[$month - 1];
            
            // Status periode
            $status = 'selesai'; // Default untuk bulan-bulan yang sudah lewat
            
            // Jika periode saat ini, status = proses
            if ($month == $currentMonth && $year == $currentYear) {
                $status = 'proses';
            }
            
            // Jika periode bulan depan (jika ada dalam seed), status = draft
            if (($month == $currentMonth + 1 && $year == $currentYear) || 
                ($month == 1 && $currentMonth == 12 && $year == $currentYear + 1)) {
                $status = 'draft';
            }
            
            // Insert dengan Query Builder untuk menyesuaikan dengan model yang menggunakan auto-increment
            DB::table('simpeg_gaji_periode')->insert([
                'nama_periode' => "Periode Gaji {$monthName} {$year}",
                'tgl_mulai' => $startDate,
                'tgl_selesai' => $endDate,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}