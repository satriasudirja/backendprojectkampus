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

        // Buat 100 data pegawai palsu
        foreach (range(1, 100) as $index) {
            SimpegPegawai::create([
                // Kolom Relasi
                'user_id'                 => $faker->randomElement($jabatanAkademikIds),
                'unit_kerja_id'           => $faker->randomElement($unitKerjaIds),
                'kode_status_pernikahan'  => $faker->randomElement($statusPernikahanIds),
                'status_aktif_id'         => $faker->randomElement($statusAktifIds),
                'jabatan_akademik_id'     => $faker->randomElement($jabatanAkademikIds),
                'suku_id'                 => $faker->randomElement($sukuIds),

                // Data Pribadi
                'nama'                    => $faker->name(),
                'nip'                     => $faker->unique()->numerify('##################'),
                'nuptk'                   => $faker->unique()->numerify('################'),
                'password'                => bcrypt('password123'),
                'nidn'                    => $faker->unique()->numerify('##########'),
                'gelar_depan'             => $faker->randomElement(['Dr.', 'Prof.', '']),
                'gelar_belakang'          => $faker->randomElement(['M.Sc.', 'Ph.D.', 'S.T.', 'M.Pd.', 'S.H.']),
                'jenis_kelamin'           => $faker->randomElement(['L', 'P']),
                'tempat_lahir'            => $faker->city(),
                'tanggal_lahir'           => $faker->date('Y-m-d', '-25 years'),
                'nama_ibu_kandung'        => $faker->name('female'), // Kolom ini sudah bisa null sesuai migrasi

                // Data Kepegawaian
                'no_sk_capeg'             => $faker->bothify('SK/???/####'),
                'tanggal_sk_capeg'        => $faker->date('Y-m-d', '-5 years'),
                'golongan_capeg'          => $faker->randomElement(['III/a', 'III/b', 'III/c', 'IV/a']),
                'tmt_capeg'               => $faker->date('Y-m-d', '-5 years'),
                'no_sk_pegawai'           => $faker->bothify('SK/???/####'),
                'tanggal_sk_pegawai'      => $faker->date('Y-m-d', '-4 years'),

                // Alamat dan Kontak
                'alamat_domisili'         => $faker->address(),
                'agama'                   => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                'golongan_darah'          => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'kota'                    => $faker->city(),
                'provinsi'                => $faker->state(),
                'kode_pos'                => $faker->postcode(),
                'no_handphone'            => $faker->phoneNumber(),
                'no_whatsapp'             => $faker->phoneNumber(), // Ditambahkan
                'no_kk'                   => $faker->numerify('################'),
                'email_pribadi'           => $faker->unique()->safeEmail(),
                'email_pegawai'           => $faker->unique()->companyEmail(), // Ditambahkan
                
                // no_telepon_domisili_kontak Dihapus
                // no_telephone_kantor Dihapus

                // Data Tambahan
                'no_ktp'                  => $faker->numerify('################'),
                'jarak_rumah_domisili'    => $faker->randomFloat(2, 1, 50),
                'npwp'                    => $faker->numerify('##.###.###.#-###.###'),
                'file_sertifikasi_dosen'  => null,
                'no_kartu_pensiun'        => $faker->bothify('PENS-########'),
                'status_kerja'            => $faker->randomElement(['Aktif', 'Tidak Aktif']),
                'kepemilikan_nohp_utama'  => $faker->randomElement(['Pribadi', 'Dinas']),
                'alamat_kependudukan'     => $faker->address(),
                'nomor_polisi'            => $faker->bothify('? #### ??'), // Ditambahkan
                'jenis_kendaraan'         => $faker->randomElement(['Motor', 'Mobil']), // Ditambahkan
                'merk_kendaraan'          => $faker->randomElement(['Honda Vario', 'Yamaha NMAX', 'Toyota Avanza', 'Daihatsu Xenia']), // Ditambahkan

                // no_kartu_bpjs Dihapus

                // File dan Dokumen
                'file_ktp'                => null,
                'file_kk'                 => null,
                'no_rekening'             => $faker->creditCardNumber(),
                'cabang_bank'             => $faker->city(),
                'nama_bank'               => $faker->randomElement(['BCA', 'BNI', 'BRI', 'Mandiri', 'BSI']),
                'file_rekening'           => null,
                'karpeg'                  => $faker->bothify('KARPEG-######'),
                'file_karpeg'             => null,
                'file_npwp'               => null,
                'file_bpjs'               => null,
                'file_bpjs_ketenagakerjaan' => null,
                'no_bpjs'                 => $faker->numerify('##############'),
                'no_bpjs_ketenagakerjaan' => $faker->numerify('##############'),
                
                // no_bpjs_pensiun Dihapus
                // file_bpjs_pensiun Dihapus

                // Data Fisik
                'tinggi_badan'            => $faker->numberBetween(150, 190),
                'berat_badan'             => $faker->numberBetween(50, 100),
                'file_tanda_tangan'       => null,

                // Audit Trail
                'modified_by'             => 'system',
                'modified_dt'             => now(),
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }
    }
}