<?php

namespace Database\Seeders;

use App\Models\SimpegPegawai;
use App\Models\SimpegGajiKomponen;
use App\Models\SimpegGajiTunjanganKhusus;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class SimpegGajiTunjanganKhususSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        // Cek apakah tabel pegawai ada datanya
        $pegawaiIds = SimpegPegawai::pluck('id')->toArray();
        
        if (empty($pegawaiIds)) {
            $this->command->error('Tabel pegawai kosong. Harap jalankan SimpegPegawaiSeeder terlebih dahulu.');
            return;
        }

        // Cek apakah tabel komponen gaji ada datanya
        $komponenTunjangan = SimpegGajiKomponen::all();
        
        if ($komponenTunjangan->isEmpty()) {
            $this->command->error('Tabel komponen gaji kosong. Harap jalankan SimpegGajiKomponenSeeder terlebih dahulu.');
            return;
        }

        $this->command->info("Found {count($pegawaiIds)} employees and {$komponenTunjangan->count()} salary components");

        $totalTunjangan = min(20, count($pegawaiIds)); // Jangan melebihi jumlah pegawai

        for ($i = 0; $i < $totalTunjangan; $i++) {
            try {
                // Pilih pegawai secara acak
                $pegawaiId = $pegawaiIds[array_rand($pegawaiIds)];

                // Pilih komponen secara acak
                $komponen = $komponenTunjangan->random();

                // Cek apakah kombinasi pegawai dan komponen sudah ada
                $exists = SimpegGajiTunjanganKhusus::where('pegawai_id', $pegawaiId)
                    ->where('komponen_id', $komponen->id)
                    ->exists();

                if ($exists) {
                    $i--; // Kurangi counter dan coba lagi
                    continue;
                }

                SimpegGajiTunjanganKhusus::create([
                    'pegawai_id' => $pegawaiId,
                    'komponen_id' => $komponen->id,
                    'nominal' => $faker->numberBetween(100000, 2000000),
                    'keterangan' => $faker->sentence(6),
                    'is_active' => $faker->boolean(80),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (($i + 1) % 5 == 0) {
                    $this->command->info("Created " . ($i + 1) . " salary allowances...");
                }

            } catch (\Exception $e) {
                $this->command->error("Error creating salary allowance {$i}: " . $e->getMessage());
                // Lanjutkan ke iterasi berikutnya
                continue;
            }
        }

        $this->command->info("Successfully created {$totalTunjangan} salary allowances");
    }
}