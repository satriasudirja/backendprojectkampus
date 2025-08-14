<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpegMasterProdiPerguruanTinggiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Mengambil ID dari tabel relasi (tanpa mengubah logika Anda)
        $uikaId = DB::table('simpeg_master_perguruan_tinggi')->where('kode', 'UIKA')->value('id');
        $uiId = DB::table('simpeg_master_perguruan_tinggi')->where('kode', 'UI')->value('id');
        $itbId = DB::table('simpeg_master_perguruan_tinggi')->where('kode', 'ITB')->value('id');
        
        $s1Id = DB::table('simpeg_jenjang_pendidikan')->where('jenjang_singkatan', 'S1')->value('id');
        $s2Id = DB::table('simpeg_jenjang_pendidikan')->where('jenjang_singkatan', 'S2')->value('id');
        $s3Id = DB::table('simpeg_jenjang_pendidikan')->where('jenjang_singkatan', 'S3')->value('id');

        // Validasi sederhana untuk memastikan ID ditemukan
        if (!$uikaId || !$s1Id) {
            $this->command->error('Data referensi (UIKA atau Jenjang S1) tidak ditemukan. Harap jalankan seeder yang relevan terlebih dahulu.');
            return;
        }

        $prodiData = [
            // UIKA Bogor
            [
                'perguruan_tinggi_id' => $uikaId,
                'jenjang_pendidikan_id' => $s1Id,
                'kode' => 'TIF-UIKA',
                'nama_prodi' => 'Teknik Informatika',
                'alamat' => 'Jl. K.H. Sholeh Iskandar Km. 2, Kedung Badak, Kota Bogor',
                'no_telp' => '(0251) 8356884',
                'akreditasi' => 'B',
                'is_aktif' => true,
            ],
            [
                'perguruan_tinggi_id' => $uikaId,
                'jenjang_pendidikan_id' => $s1Id,
                'kode' => 'SI-UIKA',
                'nama_prodi' => 'Sistem Informasi',
                'alamat' => 'Jl. K.H. Sholeh Iskandar Km. 2, Kedung Badak, Kota Bogor',
                'no_telp' => '(0251) 8356884',
                'akreditasi' => 'B',
                'is_aktif' => true,
            ],
            [
                'perguruan_tinggi_id' => $uikaId,
                'jenjang_pendidikan_id' => $s2Id,
                'kode' => 'MTI-UIKA',
                'nama_prodi' => 'Magister Teknik Informatika',
                'alamat' => 'Jl. K.H. Sholeh Iskandar Km. 2, Kedung Badak, Kota Bogor',
                'no_telp' => '(0251) 8356884',
                'akreditasi' => 'B',
                'is_aktif' => true,
            ],
            
            // Universitas Indonesia
            [
                'perguruan_tinggi_id' => $uiId,
                'jenjang_pendidikan_id' => $s1Id,
                'kode' => 'IF-UI',
                'nama_prodi' => 'Ilmu Komputer',
                'alamat' => 'Fakultas Ilmu Komputer UI, Depok',
                'no_telp' => '(021) 7863419',
                'akreditasi' => 'A',
                'is_aktif' => true,
            ],
            [
                'perguruan_tinggi_id' => $uiId,
                'jenjang_pendidikan_id' => $s2Id,
                'kode' => 'MIK-UI',
                'nama_prodi' => 'Magister Ilmu Komputer',
                'alamat' => 'Fakultas Ilmu Komputer UI, Depok',
                'no_telp' => '(021) 7863419',
                'akreditasi' => 'A',
                'is_aktif' => true,
            ],
            [
                'perguruan_tinggi_id' => $uiId,
                'jenjang_pendidikan_id' => $s3Id,
                'kode' => 'DIK-UI',
                'nama_prodi' => 'Doktor Ilmu Komputer',
                'alamat' => 'Fakultas Ilmu Komputer UI, Depok',
                'no_telp' => '(021) 7863419',
                'akreditasi' => 'A',
                'is_aktif' => true,
            ],
            
            // Institut Teknologi Bandung
            [
                'perguruan_tinggi_id' => $itbId,
                'jenjang_pendidikan_id' => $s1Id,
                'kode' => 'IF-ITB',
                'nama_prodi' => 'Teknik Informatika',
                'alamat' => 'Jl. Ganesa No.10, Lb. Siliwangi, Kec. Coblong, Kota Bandung',
                'no_telp' => '(022) 2508135',
                'akreditasi' => 'A',
                'is_aktif' => true,
            ],
            [
                'perguruan_tinggi_id' => $itbId,
                'jenjang_pendidikan_id' => $s2Id,
                'kode' => 'MIF-ITB',
                'nama_prodi' => 'Magister Informatika',
                'alamat' => 'Jl. Ganesa No.10, Lb. Siliwangi, Kec. Coblong, Kota Bandung',
                'no_telp' => '(022) 2508135',
                'akreditasi' => 'A',
                'is_aktif' => true,
            ],
        ];
        
        // --- SOLUSI: Tambahkan 'id' dan timestamps ke setiap record ---
        $dataToInsert = array_map(function ($item) use ($now) {
            $item['id'] = Str::uuid(); // INI PERBAIKAN UTAMANYA
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
            return $item;
        }, $prodiData);
        
        // Kosongkan tabel dan insert data baru
        DB::table('simpeg_master_prodi_perguruan_tinggi')->truncate();
        DB::table('simpeg_master_prodi_perguruan_tinggi')->insert($dataToInsert);
        
        $this->command->info('Seeder SimpegMasterProdiPerguruanTinggiSeeder berhasil dijalankan.');
    }
}
