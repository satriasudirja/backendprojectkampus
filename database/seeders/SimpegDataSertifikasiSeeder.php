<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class SimpegDataSertifikasiSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');
        
        // Pastikan hanya mengambil ID yang benar-benar ada
        $bidangIlmuIds = DB::table('simpeg_rumpun_bidang_ilmu')->pluck('id')->toArray();
        $jenisSertifikasiIds = DB::table('simpeg_master_jenis_sertifikasi')->pluck('id')->toArray();
        $pegawaiIds = DB::table('simpeg_pegawai')->pluck('id')->toArray();

        // Validasi data referensi
        if (empty($bidangIlmuIds) || empty($jenisSertifikasiIds) || empty($pegawaiIds)) {
            $this->command->error('ERROR: Tabel referensi kosong! Jalankan seeder berikut terlebih dahulu:');
            $this->command->info('1. SimpegRumpunBidangIlmuSeeder');
            $this->command->info('2. SimpegMasterJenisSertifikasiSeeder');
            $this->command->info('3. SimpegPegawaiSeeder');
            return;
        }

        $data = [];
        $now = Carbon::now();

        for ($i = 0; $i < 50; $i++) {
            $tglSertifikasi = $faker->dateTimeBetween('-5 years', 'now');
            
            $data[] = [
                'pegawai_id' => $faker->randomElement($pegawaiIds),
                'jenis_sertifikasi_id' => $faker->randomElement($jenisSertifikasiIds),
                'bidang_ilmu_id' => $faker->randomElement($bidangIlmuIds),
                'no_sertifikasi' => 'CERT-' . $faker->unique()->numberBetween(1000, 9999),
                'tgl_sertifikasi' => $tglSertifikasi,
                'no_registrasi' => 'REG-' . $faker->numberBetween(1000, 9999),
                'no_peserta' => 'PES-' . $faker->numberBetween(1000, 9999),
                'peran' => $faker->randomElement(['Peserta', 'Instruktur', 'Penguji']),
                'penyelenggara' => $faker->company,
                'tempat' => $faker->city,
                'lingkup' => $faker->randomElement(['Nasional', 'Internasional']),
                'tgl_input' => Carbon::instance($tglSertifikasi)->addDays(rand(1, 30)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert data per batch
        foreach (array_chunk($data, 20) as $batch) {
            DB::table('simpeg_data_sertifikasi')->insert($batch);
        }
    }
}