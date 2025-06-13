<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegPekerjaan;

class SimpegPekerjaanSeeder extends Seeder
{
    public function run()
    {
        $pekerjaan = [
            ['kode' => 'PNS', 'nama_pekerjaan' => 'Pegawai Negeri Sipil (PNS)'],
            ['kode' => 'TNI', 'nama_pekerjaan' => 'Tentara Nasional Indonesia (TNI)'],
            ['kode' => 'POLRI', 'nama_pekerjaan' => 'Kepolisian RI (POLRI)'],
            ['kode' => 'DOSEN', 'nama_pekerjaan' => 'Dosen'],
            ['kode' => 'GURU', 'nama_pekerjaan' => 'Guru'],
            ['kode' => 'DOKTER', 'nama_pekerjaan' => 'Dokter'],
            ['kode' => 'PERAWAT', 'nama_pekerjaan' => 'Perawat'],
            ['kode' => 'SWASTA', 'nama_pekerjaan' => 'Karyawan Swasta'],
            ['kode' => 'WIRA', 'nama_pekerjaan' => 'Wiraswasta / Pengusaha'],
            ['kode' => 'BUMN', 'nama_pekerjaan' => 'Pegawai BUMN'],
            ['kode' => 'PENSIUN', 'nama_pekerjaan' => 'Pensiunan'],
            ['kode' => 'IRT', 'nama_pekerjaan' => 'Mengurus Rumah Tangga'],
            ['kode' => 'PELAJAR', 'nama_pekerjaan' => 'Pelajar / Mahasiswa'],
            ['kode' => 'BLMBKRJ', 'nama_pekerjaan' => 'Belum / Tidak Bekerja'],
            ['kode' => 'LAIN', 'nama_pekerjaan' => 'Lainnya'],
        ];

        foreach ($pekerjaan as $item) {
            SimpegPekerjaan::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }

        $this->command->info('Tabel simpeg_pekerjaan berhasil di-seed.');
    }
}