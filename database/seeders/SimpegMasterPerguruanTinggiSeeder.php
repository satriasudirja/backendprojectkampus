<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SimpegMasterPerguruanTinggiSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        // Data perguruan tinggi
        $perguruanTinggi = [
            [
                'id'=>Str::uuid(),
                'kode' => 'UIKA',
                'nama_universitas' => 'Universitas Ibn Khaldun Bogor',
                'alamat' => 'Jl. K.H. Sholeh Iskandar Km. 2, Kedung Badak, Kota Bogor, Jawa Barat 16162',
                'no_telp' => '(0251) 8356884',
                'email' => 'info@uika-bogor.ac.id',
                'website' => 'https://uika-bogor.ac.id',
                'akreditasi' => 'B',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UI',
                'nama_universitas' => 'Universitas Indonesia',
                'alamat' => 'Jl. Margonda Raya, Pondok Cina, Beji, Kota Depok, Jawa Barat 16424',
                'no_telp' => '(021) 7867222',
                'email' => 'humas-ui@ui.ac.id',
                'website' => 'https://www.ui.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'ITB',
                'nama_universitas' => 'Institut Teknologi Bandung',
                'alamat' => 'Jl. Ganesa No.10, Lb. Siliwangi, Kecamatan Coblong, Kota Bandung, Jawa Barat 40132',
                'no_telp' => '(022) 2500935',
                'email' => 'humas@itb.ac.id',
                'website' => 'https://www.itb.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UGM',
                'nama_universitas' => 'Universitas Gadjah Mada',
                'alamat' => 'Bulaksumur, Caturtunggal, Kec. Depok, Kabupaten Sleman, Daerah Istimewa Yogyakarta 55281',
                'no_telp' => '(0274) 588688',
                'email' => 'info@ugm.ac.id',
                'website' => 'https://www.ugm.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'IPB',
                'nama_universitas' => 'Institut Pertanian Bogor',
                'alamat' => 'Jl. Raya Dramaga, Babakan, Kec. Dramaga, Kabupaten Bogor, Jawa Barat 16680',
                'no_telp' => '(0251) 8622642',
                'email' => 'humas@ipb.ac.id',
                'website' => 'https://www.ipb.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UNPAD',
                'nama_universitas' => 'Universitas Padjadjaran',
                'alamat' => 'Jl. Raya Bandung Sumedang KM.21, Hegarmanah, Kec. Jatinangor, Kabupaten Sumedang, Jawa Barat 45363',
                'no_telp' => '(022) 84288888',
                'email' => 'humas@unpad.ac.id',
                'website' => 'https://www.unpad.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UNDIP',
                'nama_universitas' => 'Universitas Diponegoro',
                'alamat' => 'Jl. Prof. Sudarto, Tembalang, Kec. Tembalang, Kota Semarang, Jawa Tengah 50275',
                'no_telp' => '(024) 7460012',
                'email' => 'humas@undip.ac.id',
                'website' => 'https://www.undip.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UNHAS',
                'nama_universitas' => 'Universitas Hasanuddin',
                'alamat' => 'Jl. Perintis Kemerdekaan KM.10, Tamalanrea Indah, Kec. Tamalanrea, Kota Makassar, Sulawesi Selatan 90245',
                'no_telp' => '(0411) 586200',
                'email' => 'info@unhas.ac.id',
                'website' => 'https://www.unhas.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UIN-JKT',
                'nama_universitas' => 'UIN Syarif Hidayatullah Jakarta',
                'alamat' => 'Jl. Ir H. Juanda No.95, Cemp. Putih, Kec. Ciputat, Kota Tangerang Selatan, Banten 15412',
                'no_telp' => '(021) 7401925',
                'email' => 'humas@uinjkt.ac.id',
                'website' => 'https://www.uinjkt.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id'=>Str::uuid(),
                'kode' => 'UNAIR',
                'nama_universitas' => 'Universitas Airlangga',
                'alamat' => 'Jl. Airlangga No.4 - 6, Airlangga, Kec. Gubeng, Kota Surabaya, Jawa Timur 60115',
                'no_telp' => '(031) 5914042',
                'email' => 'humas@unair.ac.id',
                'website' => 'https://www.unair.ac.id',
                'akreditasi' => 'A',
                'is_aktif' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ];
        
        // Masukkan data
        DB::table('simpeg_master_perguruan_tinggi')->insert($perguruanTinggi);
        
        $this->command->info('Berhasil menambahkan ' . count($perguruanTinggi) . ' perguruan tinggi.');
    }
}