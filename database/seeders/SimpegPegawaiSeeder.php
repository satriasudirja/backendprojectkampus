<?php

namespace Database\Seeders;

use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegStatusAktif;
use App\Models\SimpegStatusPernikahan;
use App\Models\SimpegJabatanAkademik;
use App\Models\SimpegSuku;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class SimpegPegawaiSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        // Create a number of pegawai records
        foreach (range(1, 100) as $index) {
            // Get valid foreign keys first
            $jabatanAkademik = SimpegJabatanAkademik::inRandomOrder()->first();
            $unitKerja = SimpegUnitKerja::inRandomOrder()->first();
            $statusPernikahan = SimpegStatusPernikahan::inRandomOrder()->first();
            $statusAktif = SimpegStatusAktif::inRandomOrder()->first();
            $suku = SimpegSuku::inRandomOrder()->first();
            
            // Make sure we have valid IDs before proceeding
            if (!$unitKerja || !$statusPernikahan || !$statusAktif || !$jabatanAkademik || !$suku) {
                $this->command->error('Required reference data is missing. Make sure to run required seeders first.');
                return;
            }
            
            SimpegPegawai::create([
                // Remove the UUID generation for id as PostgreSQL expects bigint
                // The id will be auto-incremented by the database
                'user_id'               => $jabatanAkademik->id,
                'unit_kerja_id'         => $unitKerja->id,
                'kode_status_pernikahan'=> $statusPernikahan->id,
                'status_aktif_id'       => $statusAktif->id,
                'jabatan_akademik_id'   => $jabatanAkademik->id,
                'suku_id'               => $suku->id,
                'nama'                  => $faker->name(),
                'nip'                   => $faker->numerify('##############'),
                'nuptk'                   => $faker->numerify('##############'),
                'password'              => bcrypt('password123'),
                'nidn'                  => $faker->numerify('############'),
                'gelar_depan'           => $faker->randomElement(['Dr.', 'Prof.', 'Ir.']),
                'gelar_belakang'        => $faker->randomElement(['M.Sc.', 'Ph.D.', 'S.T.', 'M.Pd.', 'S.H.']),
                'jenis_kelamin'         => $faker->randomElement(['L', 'P']),
                'tempat_lahir'          => $faker->city(),
                'tanggal_lahir'         => $faker->date('Y-m-d', '-20 years'),
                'nama_ibu_kandung'      => $faker->name('female'),
                'no_sk_capeg'           => $faker->bothify('SK/???/####'),
                'tanggal_sk_capeg'      => $faker->date('Y-m-d', '-5 years'),
                'golongan_capeg'        => $faker->randomElement(['III/a', 'III/b', 'III/c', 'IV/a']),
                'tmt_capeg'             => $faker->date('Y-m-d', '-5 years'),
                'no_sk_pegawai'         => $faker->bothify('SK/???/####'),
                'tanggal_sk_pegawai'    => $faker->date('Y-m-d', '-4 years'),
                'alamat_domisili'       => $faker->address(),
                'agama'                 => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                'golongan_darah'        => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'kota'                  => $faker->city(),
                'provinsi'              => $faker->state(),
                'kode_pos'              => $faker->postcode(),
                'no_telepon_domisili_kontak' => $faker->phoneNumber(),
                'no_handphone'          => $faker->phoneNumber(),
                'no_telephone_kantor'   => $faker->phoneNumber(),
                'no_kk'                 => $faker->numerify('##############'),
                'email_pribadi'         => $faker->email(),
                'no_ktp'                => $faker->numerify('################'),
                'jarak_rumah_domisili'  => $faker->numberBetween(1, 50),
                'npwp'                  => $faker->numerify('##.###.###.#-###.###'),
                'no_kartu_bpjs'         => $faker->numerify('##############'),
                'file_sertifikasi_dosen'=> 'sertifikasi_' . $faker->word . '.pdf',
                'no_kartu_pensiun'      => $faker->bothify('PENS-########'),
                'status_kerja'          => $faker->boolean(),
                'kepemilikan_nohp_utama'=> $faker->boolean(),
                'alamat_kependudukan'   => $faker->address(),
                'file_ktp'              => 'file_ktp_sample.pdf',
                'file_kk'               => 'file_kk_sample.pdf',
                'no_rekening'           => $faker->numerify('##############'),
                'cabang_bank'           => $faker->city(),
                'nama_bank'             => $faker->randomElement(['BCA', 'BNI', 'BRI', 'Mandiri', 'BSI']),
                'file_rekening'         => 'file_rekening_sample.pdf',
                'karpeg'                => $faker->bothify('KARPEG-######'),
                'file_karpeg'           => 'file_karpeg_sample.pdf',
                'file_npwp'             => 'file_npwp_sample.pdf',
                'file_bpjs'             => 'file_bpjs_sample.pdf',
                'file_bpjs_ketenagakerjaan' => 'file_bpjs_ketenagakerjaan_sample.pdf',
                'no_bpjs'               => $faker->numerify('##############'),
                'no_bpjs_ketenagakerjaan' => $faker->numerify('##############'),
                'no_bpjs_pensiun'       => $faker->numerify('##############'),
                'file_bpjs_pensiun'     => 'file_bpjs_pensiun_sample.pdf',
                'tinggi_badan'          => $faker->numberBetween(150, 190),
                'berat_badan'           => $faker->numberBetween(50, 100),
                'file_tanda_tangan'     => 'file_tanda_tangan_sample.pdf',
                'created_at'            => now(),
                'updated_at'            => now(),
                'modified_by'           => 'system',
                'modified_dt'           => now(),
            ]);
        }
    }
}