<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SimpegMasterProdiPerguruanTinggiSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        // Periksa apakah tabel sudah ada
        if (!Schema::hasTable('simpeg_master_prodi_perguruan_tinggi')) {
            $this->command->error('Tabel simpeg_master_prodi_perguruan_tinggi tidak ditemukan.');
            return;
        }
        
        // Dapatkan daftar kolom yang ada di tabel
        $columns = Schema::getColumnListing('simpeg_master_prodi_perguruan_tinggi');
        $this->command->info('Kolom yang tersedia di tabel: ' . implode(', ', $columns));
        
        // Periksa kolom wajib
        $requiredColumns = ['perguruan_tinggi_id', 'jenjang_pendidikan_id', 'kode', 'nama_prodi'];
        $missingColumns = [];
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (!empty($missingColumns)) {
            $this->command->error('Kolom wajib berikut tidak ditemukan di tabel: ' . implode(', ', $missingColumns));
            return;
        }
        
        // Periksa data di tabel terkait
        $perguruanTinggiCount = DB::table('simpeg_master_perguruan_tinggi')->count();
        $jenjangPendidikanCount = DB::table('simpeg_jenjang_pendidikan')->count();
        
        if ($perguruanTinggiCount == 0 || $jenjangPendidikanCount == 0) {
            $this->command->error('Tabel perguruan tinggi atau jenjang pendidikan masih kosong. Silakan jalankan seeder terkait terlebih dahulu.');
            return;
        }
        
        // Ambil ID perguruan tinggi untuk sampel data
        $uikaId = DB::table('simpeg_master_perguruan_tinggi')
                    ->where('kode', 'UIKA')
                    ->value('id');
        
        if (!$uikaId) {
            $uikaId = DB::table('simpeg_master_perguruan_tinggi')->first()->id ?? 1;
            $this->command->info('UIKA tidak ditemukan, menggunakan ID perguruan tinggi pertama: ' . $uikaId);
        }
                    
        $uiId = DB::table('simpeg_master_perguruan_tinggi')
                    ->where('kode', 'UI')
                    ->value('id');
        
        if (!$uiId) {
            $uiId = $uikaId; // Gunakan ID UIKA sebagai fallback
            $this->command->info('UI tidak ditemukan, menggunakan ID UIKA sebagai pengganti: ' . $uiId);
        }
                    
        $itbId = DB::table('simpeg_master_perguruan_tinggi')
                    ->where('kode', 'ITB')
                    ->value('id');
        
        if (!$itbId) {
            $itbId = $uikaId; // Gunakan ID UIKA sebagai fallback
            $this->command->info('ITB tidak ditemukan, menggunakan ID UIKA sebagai pengganti: ' . $itbId);
        }
        
        // Ambil ID jenjang pendidikan
        $s1Id = DB::table('simpeg_jenjang_pendidikan')
                    ->where('jenjang_singkatan', 'S1')
                    ->value('id');
        
        if (!$s1Id) {
            $s1Id = DB::table('simpeg_jenjang_pendidikan')->first()->id ?? 1;
            $this->command->info('Jenjang S1 tidak ditemukan, menggunakan ID jenjang pertama: ' . $s1Id);
        }
                    
        $s2Id = DB::table('simpeg_jenjang_pendidikan')
                    ->where('jenjang_singkatan', 'S2')
                    ->value('id');
        
        if (!$s2Id) {
            $s2Id = $s1Id; // Gunakan ID S1 sebagai fallback
            $this->command->info('Jenjang S2 tidak ditemukan, menggunakan ID S1 sebagai pengganti: ' . $s2Id);
        }
                    
        $s3Id = DB::table('simpeg_jenjang_pendidikan')
                    ->where('jenjang_singkatan', 'S3')
                    ->value('id');
        
        if (!$s3Id) {
            $s3Id = $s1Id; // Gunakan ID S1 sebagai fallback
            $this->command->info('Jenjang S3 tidak ditemukan, menggunakan ID S1 sebagai pengganti: ' . $s3Id);
        }
        
        // Buat template data berdasarkan kolom yang ada
        $dataTemplate = [];
        
        // Kolom wajib
        $dataTemplate['perguruan_tinggi_id'] = 0;
        $dataTemplate['jenjang_pendidikan_id'] = 0;
        $dataTemplate['kode'] = '';
        $dataTemplate['nama_prodi'] = '';
        
        // Kolom opsional
        if (in_array('alamat', $columns)) {
            $dataTemplate['alamat'] = '';
        }
        
        if (in_array('no_telp', $columns)) {
            $dataTemplate['no_telp'] = '';
        }
        
        if (in_array('akreditasi', $columns)) {
            $dataTemplate['akreditasi'] = '';
        }
        
        if (in_array('is_aktif', $columns)) {
            $dataTemplate['is_aktif'] = true;
        }
        
        if (in_array('created_at', $columns)) {
            $dataTemplate['created_at'] = $now;
        }
        
        if (in_array('updated_at', $columns)) {
            $dataTemplate['updated_at'] = $now;
        }
        
        // Data program studi
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
            
            // UI
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
            
            // ITB
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
        
        // Modifikasi data untuk mencocokkan struktur tabel yang ada
        $finalData = [];
        foreach ($prodiData as $index => $data) {
            $entry = [];
            
            // Hanya masukkan kolom yang ada di tabel
            foreach ($dataTemplate as $key => $value) {
                if (isset($data[$key])) {
                    $entry[$key] = $data[$key];
                } else {
                    $entry[$key] = $value;
                }
            }
            
            $finalData[] = $entry;
        }
        
        try {
            // Masukkan data
            DB::table('simpeg_master_prodi_perguruan_tinggi')->insert($finalData);
            
            $this->command->info('Berhasil menambahkan ' . count($finalData) . ' program studi perguruan tinggi.');
        } catch (\Exception $e) {
            $this->command->error('Gagal menambahkan data: ' . $e->getMessage());
            
            // Debugging
            $this->command->line('Detail struktur data:');
            $this->command->line(json_encode($finalData[0], JSON_PRETTY_PRINT));
        }
    }
}