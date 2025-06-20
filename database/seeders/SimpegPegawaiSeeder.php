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
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create('id_ID');

        // Ambil data referensi terlebih dahulu
        $jabatanAkademikIds = SimpegJabatanAkademik::pluck('id');
        $unitKerjaIds = SimpegUnitKerja::pluck('id');
        $statusPernikahanIds = SimpegStatusPernikahan::pluck('id');
        $statusAktifIds = SimpegStatusAktif::pluck('id');
        $sukuIds = SimpegSuku::pluck('id');

        // Pastikan semua data referensi ada
        if ($jabatanAkademikIds->isEmpty() || $unitKerjaIds->isEmpty() || $statusPernikahanIds->isEmpty() || $statusAktifIds->isEmpty() || $sukuIds->isEmpty()) {
            $this->command->error('Required reference data is missing. Make sure to run the prerequisite seeders first.');
            return;
        }

        // Dapatkan data referensi khusus
        $unitKerjaUtama = SimpegUnitKerja::where('kode_unit', '041001')->first();
        $statusPernikahanFirst = SimpegStatusPernikahan::first();
        $statusAktifFirst = SimpegStatusAktif::first();
        $sukuFirst = SimpegSuku::first();

        // STEP 1: Buat pegawai khusus untuk jabatan struktural utama
        $specialPegawai = [
            // Pengguna yang sudah ada
            [ 'nama' => 'Prof. Dr. Sutrisno, M.Pd.', 'nip' => '196501011990031001', 'jabatan_akademik' => 'Guru Besar', 'email' => 'rektor@uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => 'Prof. Dr.', 'gelar_belakang' => 'M.Pd.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Dr. Satria Sudirja, S.Kom., M.T.', 'nip' => '198505152010121002', 'jabatan_akademik' => 'Lektor Kepala', 'email' => 'dekan@ft.uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'S.Kom., M.T.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Dr. Siti Nurhasanah, S.E., M.M.', 'nip' => '197803102005012001', 'jabatan_akademik' => 'Lektor', 'email' => 'wakil.dekan1@ft.uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'S.E., M.M.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Ir. Ahmad Fauzi, M.T.', 'nip' => '198201152008011001', 'jabatan_akademik' => 'Lektor', 'email' => 'wakil.dekan2@ft.uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => 'Ir.', 'gelar_belakang' => 'M.T.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Dr. Rina Permatasari, S.Kom., M.Kom.', 'nip' => '198907122012012001', 'jabatan_akademik' => 'Asisten Ahli', 'email' => 'wakil.dekan3@ft.uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'S.Kom., M.Kom.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Budi Santoso, S.Kom., M.T.', 'nip' => '199001011015011001', 'jabatan_akademik' => 'Asisten Ahli', 'email' => 'kaprodi@ti.uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => '', 'gelar_belakang' => 'S.Kom., M.T.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Ani Suryani, S.Kom.', 'nip' => '199205102017012001', 'jabatan_akademik' => 'Tenaga Pengajar', 'email' => 'sekprodi@ti.uika-bogor.ac.id', 'role' => 'Dosen', 'gelar_depan' => '', 'gelar_belakang' => 'S.Kom.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Hendra Pratama, S.T.', 'nip' => '199306152018011001', 'jabatan_akademik' => 'Laboran', 'email' => 'keplab@ft.uika-bogor.ac.id', 'role' => 'Tenaga Kependidikan', 'gelar_depan' => '', 'gelar_belakang' => 'S.T.', 'is_admin' => false, 'password' => 'password123' ],
            [ 'nama' => 'Sari Wulandari, S.Pd.', 'nip' => '199408202019012001', 'jabatan_akademik' => 'Administrasi', 'email' => 'bagtu@ft.uika-bogor.ac.id', 'role' => 'Tenaga Kependidikan', 'gelar_depan' => '', 'gelar_belakang' => 'S.Pd.', 'is_admin' => false, 'password' => 'password123' ],
            
            // Pengguna admin yang sudah ada
            [ 'nama' => 'Muhammad Rizki, S.Kom.', 'nip' => '199512152020011001', 'jabatan_akademik' => 'Administrasi', 'email' => 'admin@ft.uika-bogor.ac.id', 'role' => 'Tenaga Kependidikan', 'gelar_depan' => '', 'gelar_belakang' => 'S.Kom.', 'is_admin' => true, 'password' => 'password123' ],
            
            // Pengguna admin baru dari seeder admin
            [ 'nama' => 'Administrator Sistem', 'nip' => '199001010001', 'jabatan_akademik' => 'Administrasi', 'email' => 'admin@example.com', 'role' => 'Tenaga Kependidikan', 'gelar_depan' => '', 'gelar_belakang' => 'S.Kom.', 'is_admin' => true, 'password' => 'admin123' ],
            [ 'nama' => 'Super Admin', 'nip' => '199001010002', 'jabatan_akademik' => 'Administrasi', 'email' => 'superadmin@example.com', 'role' => 'Tenaga Kependidikan', 'gelar_depan' => '', 'gelar_belakang' => 'S.T.', 'is_admin' => true, 'password' => 'superadmin123' ],

            // Dosen spesifik dari seeder admin
            [ 'nama' => 'Satria Sudirja', 'nip' => '089638796665', 'jabatan_akademik' => 'Dosen', 'email' => 'satria@example.com', 'role' => 'Dosen', 'gelar_depan' => '', 'gelar_belakang' => 'S.Kom.', 'is_admin' => false, 'password' => 'dosen123' ],
        ];

        // Create special pegawai dengan data lengkap
        foreach ($specialPegawai as $pegawaiData) {
            $jabatanAkademik = SimpegJabatanAkademik::where('jabatan_akademik', $pegawaiData['jabatan_akademik'])->first();
            
            if (!$jabatanAkademik) {
                // Jika jabatan tidak ditemukan, gunakan yang pertama sebagai fallback
                $jabatanAkademik = SimpegJabatanAkademik::first();
            }

            $unitKerjaToUse = $unitKerjaUtama ?? SimpegUnitKerja::first();

            SimpegPegawai::create([
                // Kolom Relasi
                'user_id' => $jabatanAkademik->id, // Placeholder, idealnya berelasi ke tabel users
                'unit_kerja_id' => $unitKerjaToUse->id,
                'kode_status_pernikahan' => $statusPernikahanFirst->id,
                'status_aktif_id' => $statusAktifFirst->id,
                'jabatan_akademik_id' => $jabatanAkademik->id,
                'suku_id' => $sukuFirst->id,
                'is_admin' => $pegawaiData['is_admin'], // Menambahkan flag admin

                // Data Pribadi
                'nama' => $pegawaiData['nama'],
                'nip' => $pegawaiData['nip'],
                'nuptk' => $pegawaiData['nip'], // Asumsi NUPTK sama dengan NIP untuk data dummy
                'password' => Hash::make($pegawaiData['password']),
                'nidn' => substr($pegawaiData['nip'], 0, 10), // Asumsi NIDN adalah 10 digit pertama NIP
                'gelar_depan' => $pegawaiData['gelar_depan'],
                'gelar_belakang' => $pegawaiData['gelar_belakang'],
                'jenis_kelamin' => $faker->randomElement(['L', 'P']),
                'tempat_lahir' => $faker->city(),
                'tanggal_lahir' => $faker->date('Y-m-d', '-35 years'),
                'nama_ibu_kandung' => $faker->name('female'),

                // Data Kepegawaian
                'no_sk_capeg' => $faker->bothify('SK/???/####'),
                'tanggal_sk_capeg' => $faker->date('Y-m-d', '-10 years'),
                'golongan_capeg' => $faker->randomElement(['III/c', 'III/d', 'IV/a', 'IV/b']),
                'tmt_capeg' => $faker->date('Y-m-d', '-10 years'),
                'no_sk_pegawai' => $faker->bothify('SK/???/####'),
                'tanggal_sk_pegawai' => $faker->date('Y-m-d', '-8 years'),

                // Alamat dan Kontak
                'alamat_domisili' => $faker->address(),
                'agama' => 'Islam',
                'golongan_darah' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'kota' => 'Bogor',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '16610',
                'no_handphone' => $faker->phoneNumber(),
                'no_whatsapp' => $faker->phoneNumber(),
                'no_kk' => $faker->numerify('################'),
                'email_pribadi' => $pegawaiData['email'],
                'email_pegawai' => $pegawaiData['email'],

                // Data Tambahan
                'no_ktp' => $faker->numerify('################'),
                'jarak_rumah_domisili' => $faker->randomFloat(2, 5, 25),
                'npwp' => $faker->numerify('##.###.###.#-###.###'),
                'status_kerja' => 'Aktif',
                // ... sisa kolom lainnya bisa ditambahkan di sini jika ada
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Created ' . count($specialPegawai) . ' special employees');

        // STEP 2: Buat pegawai random dengan data lengkap
        $randomCount = 70; 
        
        foreach (range(1, $randomCount) as $index) {
            SimpegPegawai::create([
                // Kolom Relasi
                'user_id' => $faker->randomElement($jabatanAkademikIds),
                'unit_kerja_id' => $faker->randomElement($unitKerjaIds),
                'kode_status_pernikahan' => $faker->randomElement($statusPernikahanIds),
                'status_aktif_id' => $faker->randomElement($statusAktifIds),
                'jabatan_akademik_id' => $faker->randomElement($jabatanAkademikIds),
                'suku_id' => $faker->randomElement($sukuIds),
                'is_admin' => false, // Default untuk pegawai random

                // Data Pribadi
                'nama' => $faker->name(),
                'nip' => $faker->unique()->numerify('##################'),
                'nuptk' => $faker->unique()->numerify('################'),
                'password' => Hash::make('password123'),
                'nidn' => $faker->unique()->numerify('##########'),
                'gelar_depan' => $faker->randomElement(['Dr.', 'Prof.', 'Ir.', '']),
                'gelar_belakang' => $faker->randomElement(['M.Sc.', 'Ph.D.', 'S.T.', 'M.Pd.']),
                'jenis_kelamin' => $faker->randomElement(['L', 'P']),
                'tempat_lahir' => $faker->city(),
                'tanggal_lahir' => $faker->date('Y-m-d', '-35 years'),
                'nama_ibu_kandung' => $faker->name('female'),
                'status_kerja' => $faker->randomElement(['Aktif', 'Tidak Aktif']),
                'email_pribadi' => $faker->unique()->safeEmail(),
                'email_pegawai' => $faker->unique()->companyEmail(),
                // ... sisa kolom lainnya
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Created ' . $randomCount . ' random employees');
        $this->command->info('Total employees created: ' . (count($specialPegawai) + $randomCount));
    }
}
