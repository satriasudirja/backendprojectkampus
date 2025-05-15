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
        
        // Ambil data lain yang diperlukan
        $unitKerja = SimpegUnitKerja::first();
        $statusPernikahan = SimpegStatusPernikahan::first();
        $statusAktif = SimpegStatusAktif::first();
        $suku = SimpegSuku::first();
        
        // Pastikan semua data tersedia
        if (!$unitKerja || !$statusPernikahan || !$statusAktif || !$jabatanAkademik || !$suku) {
            $this->command->error('Required reference data is missing. Make sure to run required seeders first.');
            return;
        }
        
        // Buat pegawai admin dengan data tetap (fixed)
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
            'nama_ibu_kandung'      => 'Ibu Administrator',
            'alamat_domisili'       => 'Jl. Admin No. 1, Jakarta',
            'agama'                 => 'Islam',
            'golongan_darah'        => 'O',
            'kota'                  => 'Jakarta',
            'provinsi'              => 'DKI Jakarta',
            'kode_pos'              => '12345',
            'no_handphone'          => '081234567890',
            'no_kk'                 => '1234567890123456',
            'email_pribadi'         => 'admin@example.com',
            'no_ktp'                => '1234567890123456',
            'status_kerja'          => 'Aktif',
            'modified_by'           => 'system',
            'modified_dt'           => now(),
        ]);
        
        // Tambahkan Super Admin kedua jika diperlukan
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
            'no_kk'                 => '1234567890123457',
            'email_pribadi'         => 'superadmin@example.com',
            'no_ktp'                => '1234567890123457',
            'status_kerja'          => 'Aktif',
            'modified_by'           => 'system',
            'modified_dt'           => now(),
        ]);
    }
}