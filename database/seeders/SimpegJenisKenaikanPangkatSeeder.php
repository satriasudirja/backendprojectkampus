<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\SimpegJenisKenaikanPangkat;

class SimpegJenisKenaikanPangkatSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode' => 'K', 'jenis_pangkat' => 'Kopertis'],    
            ['kode' => 'L', 'jenis_pangkat' => 'LLDIKTI4'],     
            ['kode' => 'U', 'jenis_pangkat' => 'Universitas'],  
            ['kode' => 'Y', 'jenis_pangkat' => 'Yayasan'],      
        ];
        
        foreach ($data as $item) {
            SimpegJenisKenaikanPangkat::firstOrCreate(
                ['kode' => $item['kode']],
                ['jenis_pangkat' => $item['jenis_pangkat']]  
            );
        }
    }
}