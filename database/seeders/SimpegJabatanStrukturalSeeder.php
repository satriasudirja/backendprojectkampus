<?php

namespace Database\Seeders;

use App\Models\SimpegJabatanStruktural;
use App\Models\SimpegUnitKerja;
use App\Models\JenisJabatanStruktural;
use App\Models\SimpegMasterPangkat;
use App\Models\SimpegEselon;
use Illuminate\Database\Seeder;

class SimpegJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'kode' => '001',
                'singkatan' => 'REK',
                'unit_kerja_id' => SimpegUnitKerja::where('kode_unit', '041001')->first()->id,
                'jenis_jabatan_struktural_id' => JenisJabatanStruktural::where('kode', '10000')->first()->id,
                'pangkat_id' => SimpegMasterPangkat::where('pangkat', 'IV/e')->first()->id,
                'eselon_id' => SimpegEselon::where('kode', '1I')->first()->id,
                'alamat_email' => 'rektor@uika-bogor.ac.id',
                'beban_sks' => 6,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => 'Pimpinan Tertinggi Universitas',
                'parent_jabatan' => null,
            ],
            [
                'kode' => '002',
                'singkatan' => 'WR I',
                'unit_kerja_id' => SimpegUnitKerja::where('kode_unit', '041001')->first()->id,
                'jenis_jabatan_struktural_id' => JenisJabatanStruktural::where('kode', '11000')->first()->id,
                'pangkat_id' => SimpegMasterPangkat::where('pangkat', 'IV/d')->first()->id,
                'eselon_id' => SimpegEselon::where('kode', '12')->first()->id,
                'alamat_email' => 'wr1@uika-bogor.ac.id',
                'beban_sks' => 4,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => 'Wakil Rektor Bidang Akademik',
                'parent_jabatan' => '001',
            ],
            [
                'kode' => '003',
                'singkatan' => 'DEKAN',
                'unit_kerja_id' => SimpegUnitKerja::where('kode_unit', '01')->first()->id,
                'jenis_jabatan_struktural_id' => JenisJabatanStruktural::where('kode', '12000')->first()->id,
                'pangkat_id' => SimpegMasterPangkat::where('pangkat', 'IV/c')->first()->id,
                'eselon_id' => SimpegEselon::where('kode', '21')->first()->id,
                'alamat_email' => 'dekan.fkip@uika-bogor.ac.id',
                'beban_sks' => 4,
                'is_pimpinan' => true,
                'aktif' => true,
                'keterangan' => 'Dekan Fakultas Keguruan dan Ilmu Pendidikan',
                'parent_jabatan' => '001',
            ],
            // Tambahkan data jabatan lainnya sesuai kebutuhan
        ];

        foreach ($data as $item) {
            SimpegJabatanStruktural::create($item);
        }
    }
}