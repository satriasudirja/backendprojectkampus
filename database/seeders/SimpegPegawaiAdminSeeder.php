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

class SimpegPegawaiAdminSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        // Cari jabatan akademik dengan role_id = 1
        $jabatanAkademik = SimpegJabatanAkademik::where('role_id', 1)->first();
        
        // Jika tidak ada, ambil yang ada
        if (!$jabatanAkademik) {
            $jabatanAkademik = SimpegJabatanAkademik::first();
        }
        
        $pp = SimpegJabatanAkademik::where('role_id', 2)->first();
        
        // Jika tidak ada, ambil yang ada
        if (!$pp) {
            $pp = SimpegJabatanAkademik::first();
        }
        
        // Ambil data lain yang diperlukan
        $unitKerja = SimpegUnitKerja::first();
        $statusPernikahan = SimpegStatusPernikahan::first();
        $statusAktif = SimpegStatusAktif::first();
        $suku = SimpegSuku::first();
        
        // Pastikan semua data tersedia
        if (!$unitKerja || !$statusPernikahan || !$statusAktif || !$jabatanAkademik || !$suku || !$pp) {
            $this->command->error('Required reference data is missing. Make sure to run required seeders first.');
            return;
        }
        
        // Buat pegawai admin dengan data tetap (fixed) - Updated
        SimpegPegawai::create([
            'user_id'               => $jabatanAkademik->id,
            'unit_kerja_id'         => $unitKerja->id,
            'kode_status_pernikahan'=> $statusPernikahan->id,
            'status_aktif_id'       => $statusAktif->id,
            'jabatan_akademik_id'   => $jabatanAkademik->id,
            'suku_id'               => $suku->id,
            'nama'                  => 'Administrator Sistem',
            'nip'                   => '199001010001',
            'nuptk'                 => '199001010001',
            'password'              => bcrypt('admin123'),
            'nidn'                  => '1990010100',
            'gelar_depan'           => '',
            'gelar_belakang'        => 'S.Kom.',
            'jenis_kelamin'         => 'L',
            'tempat_lahir'          => 'Jakarta',
            'tanggal_lahir'         => '1990-01-01',
            'nama_ibu_kandung'      => 'Ibu Administrator',  // Now nullable
            'alamat_domisili'       => 'Jl. Admin No. 1, Jakarta',
            'agama'                 => 'Islam',
            'golongan_darah'        => 'O',
            'kota'                  => 'Jakarta',
            'provinsi'              => 'DKI Jakarta',
            'kode_pos'              => '12345',
            'no_handphone'          => '081234567890',
            'no_whatsapp'           => '081234567890',        // Added
            'no_kk'                 => '1234567890123456',
            'email_pribadi'         => 'admin@example.com',
            'email_pegawai'         => 'admin.sistem@company.com', // Added
            'no_ktp'                => '1234567890123456',
            'status_kerja'          => 'Aktif',
            'nomor_polisi'          => 'B 1234 ABC',          // Added
            'jenis_kendaraan'       => 'Motor',               // Added
            'merk_kendaraan'        => 'Honda Vario',         // Added
            'modified_by'           => 'system',
            'modified_dt'           => now(),
            
            // Removed fields (commented out):
            // 'no_kartu_bpjs'         => '1234567890',
            // 'no_bpjs_pensiun'       => '1234567890',
            // 'no_telepon_domisili_kontak' => '0212345678',
            // 'no_telephone_kantor'   => '0212345679',
        ]);
        
        // Tambahkan Super Admin kedua jika diperlukan - Updated
        SimpegPegawai::create([
            'user_id'               => $jabatanAkademik->id,
            'unit_kerja_id'         => $unitKerja->id,
            'kode_status_pernikahan'=> $statusPernikahan->id,
            'status_aktif_id'       => $statusAktif->id,
            'jabatan_akademik_id'   => $jabatanAkademik->id,
            'suku_id'               => $suku->id,
            'nama'                  => 'Super Admin',
            'nip'                   => '199001010002',
            'nuptk'                 => '199001010002',
            'password'              => bcrypt('superadmin123'),
            'nidn'                  => '1990010101',
            'gelar_depan'           => '',
            'gelar_belakang'        => 'S.T.',
            'jenis_kelamin'         => 'L',
            'tempat_lahir'          => 'Bandung',
            'tanggal_lahir'         => '1990-01-02',
            'nama_ibu_kandung'      => 'Ibu Super Admin',
            'alamat_domisili'       => 'Jl. Super Admin No. 1, Bandung',
            'agama'                 => 'Islam',
            'golongan_darah'        => 'A',
            'kota'                  => 'Bandung',
            'provinsi'              => 'Jawa Barat',
            'kode_pos'              => '40123',
            'no_handphone'          => '081234567891',
            'no_whatsapp'           => '081234567891',        // Added
            'no_kk'                 => '1234567890123457',
            'email_pribadi'         => 'superadmin@example.com',
            'email_pegawai'         => 'superadmin@company.com', // Added
            'no_ktp'                => '1234567890123457',
            'status_kerja'          => 'Aktif',
            'nomor_polisi'          => 'D 5678 XYZ',          // Added
            'jenis_kendaraan'       => 'Mobil',               // Added
            'merk_kendaraan'        => 'Toyota Avanza',       // Added
            'modified_by'           => 'system',
            'modified_dt'           => now(),
        ]);
        
        // Tambahkan Dosen - Updated
        SimpegPegawai::create([
            'user_id'               => $jabatanAkademik->id,
            'unit_kerja_id'         => $unitKerja->id,
            'kode_status_pernikahan'=> $statusPernikahan->id,
            'status_aktif_id'       => $statusAktif->id,
            'jabatan_akademik_id'   => $pp->id,
            'suku_id'               => $suku->id,
            'nama'                  => 'Satria Sudirja',
            'nip'                   => '089638796665',
            'nuptk'                 => '089638796666',
            'password'              => bcrypt('dosen123'),
            'nidn'                  => '19900101110',
            'gelar_depan'           => '',
            'gelar_belakang'        => 'S.Kom.',
            'jenis_kelamin'         => 'L',
            'tempat_lahir'          => 'Jakarta',
            'tanggal_lahir'         => '1990-01-01',
            'nama_ibu_kandung'      => 'Ibu Satria',          // Can be null now
            'alamat_domisili'       => 'Jl. Dosen No. 1, Jakarta',
            'agama'                 => 'Islam',
            'golongan_darah'        => 'O',
            'kota'                  => 'Jakarta',
            'provinsi'              => 'DKI Jakarta',
            'kode_pos'              => '12345',
            'no_handphone'          => '081234567892',
            'no_whatsapp'           => '081234567892',        // Added
            'no_kk'                 => '1234567890123458',
            'email_pribadi'         => 'satria@example.com',
            'email_pegawai'         => 'satria.sudirja@company.com', // Added
            'no_ktp'                => '1234567890123458',
            'status_kerja'          => 'Aktif',
            'nomor_polisi'          => 'B 9999 DEF',          // Added
            'jenis_kendaraan'       => 'Motor',               // Added
            'merk_kendaraan'        => 'Yamaha NMAX',         // Added
            'modified_by'           => 'system',
            'modified_dt'           => now(),
        ]);
    }
}