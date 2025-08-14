<?php

namespace Database\Seeders;

use App\Models\SimpegPegawai;
use App\Models\SimpegUser;
use App\Models\SimpegUserRole;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegStatusAktif;
use App\Models\SimpegStatusPernikahan;
use App\Models\SimpegJabatanAkademik;
use App\Models\SimpegSuku;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

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

        // Hapus data lama terlebih dahulu untuk menghindari konflik (PostgreSQL)
        $this->command->info('Cleaning existing data...');
        
        // Untuk PostgreSQL, disable triggers sementara
        DB::statement('SET session_replication_role = replica;');
        
        // Hapus data dalam urutan yang benar (child dulu, parent kemudian)
        SimpegUser::truncate();
        SimpegPegawai::truncate();
        
        // Enable triggers kembali
        DB::statement('SET session_replication_role = DEFAULT;');

        try {
            DB::transaction(function () use ($faker) {
                $this->command->info('Starting transaction...');

                // Validasi dan ambil data referensi
                $this->command->info('Loading reference data...');
                
                $jabatanAkademik = SimpegJabatanAkademik::all();
                $roles = SimpegUserRole::all();
                $unitKerjaIds = SimpegUnitKerja::pluck('id');
                $statusPernikahanIds = SimpegStatusPernikahan::pluck('id');
                $statusAktifIds = SimpegStatusAktif::pluck('id');
                $sukuIds = SimpegSuku::pluck('id');

                // Log jumlah data referensi
                $this->command->info("Reference data counts:");
                $this->command->info("- Jabatan Akademik: {$jabatanAkademik->count()}");
                $this->command->info("- Roles: {$roles->count()}");
                $this->command->info("- Unit Kerja: {$unitKerjaIds->count()}");
                $this->command->info("- Status Pernikahan: {$statusPernikahanIds->count()}");
                $this->command->info("- Status Aktif: {$statusAktifIds->count()}");
                $this->command->info("- Suku: {$sukuIds->count()}");

                // Validasi data referensi
                if ($jabatanAkademik->isEmpty()) {
                    throw new Exception('Tabel simpeg_jabatan_akademik kosong!');
                }
                if ($roles->isEmpty()) {
                    throw new Exception('Tabel simpeg_users_roles kosong!');
                }
                if ($unitKerjaIds->isEmpty()) {
                    throw new Exception('Tabel simpeg_unit_kerja kosong!');
                }
                if ($statusPernikahanIds->isEmpty()) {
                    throw new Exception('Tabel simpeg_status_pernikahan kosong!');
                }
                if ($statusAktifIds->isEmpty()) {
                    throw new Exception('Tabel simpeg_status_aktif kosong!');
                }
                if ($sukuIds->isEmpty()) {
                    throw new Exception('Tabel simpeg_suku kosong!');
                }

                // Cari role yang dibutuhkan
                $dosenRole = $roles->where('nama', 'Dosen')->first();
                $tendikRole = $roles->where('nama', 'Tenaga Kependidikan')->first();

                if (!$dosenRole) {
                    throw new Exception('Role "Dosen" tidak ditemukan di tabel simpeg_users_roles!');
                }
                if (!$tendikRole) {
                    throw new Exception('Role "Tenaga Kependidikan" tidak ditemukan di tabel simpeg_users_roles!');
                }

                $this->command->info("Found roles - Dosen ID: {$dosenRole->id}, Tendik ID: {$tendikRole->id}");

                // Ambil data default untuk pegawai khusus
                $statusPernikahanFirst = SimpegStatusPernikahan::first();
                $statusAktifFirst = SimpegStatusAktif::first();
                $sukuFirst = SimpegSuku::first();

                // STEP 1: Buat pegawai khusus
                $specialPegawai = [
                    [
                        'nama' => 'AdminSIMPEG',
                        'nip' => '085156411620',
                        'jabatan_akademik' => 'Administrasi',
                        'email' => 'admin@ft.uika-bogor.ac.id',
                        'role' => 'Tenaga Kependidikan',
                        'is_admin' => true
                    ],
                ];

                $this->command->info('Creating special employees...');

                foreach ($specialPegawai as $index => $pegawaiData) {
                    try {
                        // Cari jabatan akademik
                        $jabatanRecord = $jabatanAkademik->where('jabatan_akademik', $pegawaiData['jabatan_akademik'])->first();
                        if (!$jabatanRecord) {
                            // Jika tidak ditemukan, ambil yang pertama sebagai fallback
                            $jabatanRecord = $jabatanAkademik->first();
                            $this->command->warn("Jabatan '{$pegawaiData['jabatan_akademik']}' tidak ditemukan, menggunakan: {$jabatanRecord->jabatan_akademik}");
                        }

                        $roleRecord = ($pegawaiData['role'] === 'Dosen') ? $dosenRole : $tendikRole;
                        $tanggalLahir = $faker->date('Y-m-d', '-35 years');

                        $this->command->info("Creating special employee: {$pegawaiData['nama']}");

                        $pegawaiAttributes = [
                            'role_id' => $roleRecord->id,
                            'jabatan_akademik_id' => $jabatanRecord->id,
                            'unit_kerja_id' => $unitKerjaIds->first(),
                            'kode_status_pernikahan' => $statusPernikahanFirst->id,
                            'status_aktif_id' => $statusAktifFirst->id,
                            'suku_id' => $sukuFirst->id,
                            'is_admin' => $pegawaiData['is_admin'],
                            'nama' => $pegawaiData['nama'],
                            'nip' => $pegawaiData['nip'],
                            'nuptk' => $pegawaiData['nip'],
                            'nidn' => substr($pegawaiData['nip'], 0, 10),
                            'email_pribadi' => $pegawaiData['email'],
                            'tanggal_lahir' => $tanggalLahir,
                            'jenis_kelamin' => $faker->randomElement(['Laki-laki', 'Perempuan']),
                            'tempat_lahir' => $faker->city,
                            'agama' => 'Islam',
                        ];

                        $pegawai = SimpegPegawai::create($pegawaiAttributes);
                        $this->command->info("✓ Pegawai created with ID: {$pegawai->id}");

                        $userAttributes = [
                            'pegawai_id' => $pegawai->id,
                            'username' => $pegawai->nip,
                            'password' => Hash::make(date('dmY', strtotime($tanggalLahir))),
                            'is_active' => true,
                        ];

                        $user = SimpegUser::create($userAttributes);
                        $this->command->info("✓ User created with ID: {$user->id}");

                    } catch (Exception $e) {
                        $this->command->error("✗ Failed to create special employee {$index}: " . $e->getMessage());
                        throw $e;
                    }
                }

                // Verifikasi pegawai khusus tersimpan
                $currentCount = SimpegPegawai::count();
                $this->command->info("Special employees created. Current count: {$currentCount}");

                // // STEP 2: Buat pegawai random
                // $this->command->info('Creating random employees...');
                
                // for ($i = 0; $i < 70; $i++) {
                //     try {
                //         $isDosen = $faker->boolean(70); // 70% kemungkinan dosen
                //         $roleId = $isDosen ? $dosenRole->id : $tendikRole->id;
                //         $tanggalLahir = $faker->date('Y-m-d', '-30 years');
                //         $nip = $faker->unique()->numerify('19##############');

                //         $pegawaiAttributes = [
                //             'role_id' => $roleId,
                //             'jabatan_akademik_id' => $isDosen ? $jabatanAkademik->random()->id : null,
                //             'unit_kerja_id' => $unitKerjaIds->random(),
                //             'kode_status_pernikahan' => $statusPernikahanIds->random(),
                //             'status_aktif_id' => $statusAktifIds->random(),
                //             'suku_id' => $sukuIds->random(),
                //             'is_admin' => false,
                //             'nama' => $faker->name,
                //             'nip' => $nip,
                //             'nuptk' => $nip,
                //             'nidn' => $isDosen ? substr($nip, 0, 10) : null,
                //             'email_pribadi' => $faker->unique()->safeEmail,
                //             'tanggal_lahir' => $tanggalLahir,
                //             'jenis_kelamin' => $faker->randomElement(['Laki-laki', 'Perempuan']),
                //             'tempat_lahir' => $faker->city,
                //             'agama' => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                //         ];

                //         $pegawai = SimpegPegawai::create($pegawaiAttributes);

                //         $userAttributes = [
                //             'pegawai_id' => $pegawai->id,
                //             'username' => $pegawai->nip,
                //             'password' => Hash::make(date('dmY', strtotime($tanggalLahir))),
                //             'is_active' => true,
                //         ];

                //         SimpegUser::create($userAttributes);

                //         if (($i + 1) % 10 == 0) {
                //             $this->command->info("Created " . ($i + 1) . " random employees...");
                //         }

                //     } catch (Exception $e) {
                //         $this->command->error("✗ Failed to create random employee {$i}: " . $e->getMessage());
                //         throw $e;
                //     }
                // }

                // Final verification
                $finalPegawaiCount = SimpegPegawai::count();
                $finalUserCount = SimpegUser::count();
                
                $this->command->info("Final verification:");
                $this->command->info("- Total Pegawai: {$finalPegawaiCount}");
                $this->command->info("- Total Users: {$finalUserCount}");
                
                // if ($finalPegawaiCount != 73 || $finalUserCount != 73) {
                //     throw new Exception("Expected 73 employees and users, got {$finalPegawaiCount} employees and {$finalUserCount} users");
                // }

                $this->command->info('✓ All employees and users created successfully!');
            });

        } catch (Exception $e) {
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }

        $this->command->info('🎉 Database seeding completed successfully!');
    }
}