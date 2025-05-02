<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HubunganKerja;

class SimpegHubunganKerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'kode' => 'H1',
                'nama_hub_kerja' => 'Tetap Yayasan Dosen',
                'status_aktif' => true,
                'pns' => false,
            ],
            [
                'kode' => 'H2',
                'nama_hub_kerja' => 'Tetap Yayasan Karyawan',
                'status_aktif' => true,
                'pns' => false,
            ],
            [
                'kode' => 'H3',
                'nama_hub_kerja' => 'PNS/DPK',
                'status_aktif' => true,
                'pns' => true,
            ],
            [
                'kode' => 'H4',
                'nama_hub_kerja' => 'Dosen Tidak Tetap',
                'status_aktif' => true,
                'pns' => false,
            ],
            [
                'kode' => 'H5',
                'nama_hub_kerja' => 'Kontrak',
                'status_aktif' => true,
                'pns' => false,
            ],
            [
                'kode' => 'H6',
                'nama_hub_kerja' => 'Kontrak Fakultas',
                'status_aktif' => true,
                'pns' => false,
            ],
        ];

        foreach ($data as $item) {
            HubunganKerja::create($item);
        }
    }
}
