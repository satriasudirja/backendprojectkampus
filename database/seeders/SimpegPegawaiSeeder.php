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
use Illuminate\Support\Facades\Hash;

class SimpegPegawaiSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        // Buat pegawai khusus untuk jabatan struktural utama
        $specialPegawai = [
            [
                'nama' => 'Prof. Dr. Sutrisno, M.Pd.',
                'nip' => '196501011990031001',
                'jabatan_akademik' => 'Guru Besar',
                'email' => 'rektor@uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Dr. Satria Sudirja, S.Kom., M.T.',
                'nip' => '198505152010121002',
                'jabatan_akademik' => 'Lektor Kepala', 
                'email' => 'dekan@ft.uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Dr. Siti Nurhasanah, S.E., M.M.',
                'nip' => '197803102005012001',
                'jabatan_akademik' => 'Lektor',
                'email' => 'wakil.dekan1@ft.uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Ir. Ahmad Fauzi, M.T.',
                'nip' => '198201152008011001',
                'jabatan_akademik' => 'Lektor',
                'email' => 'wakil.dekan2@ft.uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Dr. Rina Permatasari, S.Kom., M.Kom.',
                'nip' => '198907122012012001',
                'jabatan_akademik' => 'Asisten Ahli',
                'email' => 'wakil.dekan3@ft.uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Budi Santoso, S.Kom., M.T.',
                'nip' => '199001011015011001',
                'jabatan_akademik' => 'Asisten Ahli',
                'email' => 'kaprodi@ti.uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Ani Suryani, S.Kom.',
                'nip' => '199205102017012001',
                'jabatan_akademik' => 'Tenaga Pengajar',
                'email' => 'sekprodi@ti.uika-bogor.ac.id',
                'role' => 'Dosen'
            ],
            [
                'nama' => 'Hendra Pratama, S.T.',
                'nip' => '199306152018011001',
                'jabatan_akademik' => 'Laboran',
                'email' => 'keplab@ft.uika-bogor.ac.id',
                'role' => 'Tenaga Kependidikan'
            ],
            [
                'nama' => 'Sari Wulandari, S.Pd.',
                'nip' => '199408202019012001',
                'jabatan_akademik' => 'Administrasi',
                'email' => 'bagtu@ft.uika-bogor.ac.id',
                'role' => 'Tenaga Kependidikan'
            ],
            [
                'nama' => 'Muhammad Rizki, S.Kom.',
                'nip' => '199512152020011001',
                'jabatan_akademik' => 'Administrasi',
                'email' => 'admin@ft.uika-bogor.ac.id',
                'role' => 'Tenaga Kependidikan'
            ]
        ];

        // Get reference data - FIXED: Use id instead of kode_unit
        $unitKerja = SimpegUnitKerja::where('kode_unit', '041001')->first();
        $statusPernikahan = SimpegStatusPernikahan::first();
        $statusAktif = SimpegStatusAktif::first();
        $suku = SimpegSuku::first();

        if (!$unitKerja || !$statusPernikahan || !$statusAktif || !$suku) {
            $this->command->error('Required reference data is missing. Make sure to run required seeders first.');
            return;
        }

        // Create special pegawai
        foreach ($specialPegawai as $index => $pegawaiData) {
            $jabatanAkademik = SimpegJabatanAkademik::where('jabatan_akademik', $pegawaiData['jabatan_akademik'])->first();
            
            if (!$jabatanAkademik) {
                $jabatanAkademik = SimpegJabatanAkademik::first();
            }

            SimpegPegawai::create([
                'user_id' => $jabatanAkademik->id,
                'unit_kerja_id' => $unitKerja->id, // FIXED: Use id instead of kode_unit
                'kode_status_pernikahan' => $statusPernikahan->id,
                'status_aktif_id' => $statusAktif->id,
                'jabatan_akademik_id' => $jabatanAkademik->id,
                'suku_id' => $suku->id,
                'nama' => $pegawaiData['nama'],
                'nip' => $pegawaiData['nip'],
                'nuptk' => $pegawaiData['nip'],
                'password' => Hash::make('password123'),
                'nidn' => substr($pegawaiData['nip'], 0, 10),
                'gelar_depan' => '',
                'gelar_belakang' => '',
                'jenis_kelamin' => $faker->randomElement(['L', 'P']),
                'tempat_lahir' => $faker->city(),
                'tanggal_lahir' => $faker->date('Y-m-d', '-25 years'),
                'nama_ibu_kandung' => $faker->name('female'),
                'alamat_domisili' => $faker->address(),
                'agama' => 'Islam',
                'golongan_darah' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'kota' => 'Bogor',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '16610',
                'no_handphone' => $faker->phoneNumber(),
                'no_kk' => $faker->numerify('##############'),
                'email_pribadi' => $pegawaiData['email'],
                'no_ktp' => $faker->numerify('################'),
                'status_kerja' => 'Aktif',
                'modified_by' => 'system',
                'modified_dt' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create additional random pegawai
        foreach (range(1, 20) as $index) {
            $jabatanAkademik = SimpegJabatanAkademik::inRandomOrder()->first();
            $randomUnitKerja = SimpegUnitKerja::inRandomOrder()->first(); // Get random unit for variety
            
            SimpegPegawai::create([
                'user_id' => $jabatanAkademik->id,
                'unit_kerja_id' => $randomUnitKerja->id, // FIXED: Use id instead of kode_unit
                'kode_status_pernikahan' => $statusPernikahan->id,
                'status_aktif_id' => $statusAktif->id,
                'jabatan_akademik_id' => $jabatanAkademik->id,
                'suku_id' => $suku->id,
                'nama' => $faker->name(),
                'nip' => $faker->numerify('##############'),
                'nuptk' => $faker->numerify('##############'),
                'password' => Hash::make('password123'),
                'nidn' => $faker->numerify('############'),
                'gelar_depan' => $faker->randomElement(['', 'Dr.', 'Prof.', 'Ir.']),
                'gelar_belakang' => $faker->randomElement(['S.Kom.', 'M.T.', 'M.Sc.', 'Ph.D.', 'S.T.', 'M.Pd.']),
                'jenis_kelamin' => $faker->randomElement(['L', 'P']),
                'tempat_lahir' => $faker->city(),
                'tanggal_lahir' => $faker->date('Y-m-d', '-25 years'),
                'nama_ibu_kandung' => $faker->name('female'),
                'alamat_domisili' => $faker->address(),
                'agama' => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                'golongan_darah' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'kota' => $faker->city(),
                'provinsi' => $faker->state(),
                'kode_pos' => $faker->postcode(),
                'no_handphone' => $faker->phoneNumber(),
                'no_kk' => $faker->numerify('##############'),
                'email_pribadi' => $faker->email(),
                'no_ktp' => $faker->numerify('################'),
                'status_kerja' => 'Aktif',
                'modified_by' => 'system',
                'modified_dt' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}