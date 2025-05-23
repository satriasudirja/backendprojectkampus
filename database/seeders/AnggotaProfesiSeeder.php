<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AnggotaProfesi;
use Carbon\Carbon;

class AnggotaProfesiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $anggotaProfesi = [
            [
                'nama_organisasi' => 'Ikatan Dokter Indonesia (IDI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2018 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subDays(10),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(6),
                'updated_at' => Carbon::now()->subDays(10),
            ],
            [
                'nama_organisasi' => 'Persatuan Perawat Nasional Indonesia (PPNI)',
                'peran_kedudukan' => 'Sekretaris Cabang',
                'waktu_keanggotaan' => '2020 - 2023',
                'tanggal_sinkron' => Carbon::now()->subDays(5),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(4),
                'updated_at' => Carbon::now()->subDays(5),
            ],
            [
                'nama_organisasi' => 'Ikatan Akuntan Indonesia (IAI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2019 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subDays(3),
                'status_pengajuan' => 'pending',
                'created_at' => Carbon::now()->subMonths(3),
                'updated_at' => Carbon::now()->subDays(3),
            ],
            [
                'nama_organisasi' => 'Persatuan Insinyur Indonesia (PII)',
                'peran_kedudukan' => 'Bendahara',
                'waktu_keanggotaan' => '2017 - 2022',
                'tanggal_sinkron' => null,
                'status_pengajuan' => 'draft',
                'created_at' => Carbon::now()->subMonths(2),
                'updated_at' => Carbon::now()->subMonths(2),
            ],
            [
                'nama_organisasi' => 'Himpunan Psikologi Indonesia (HIMPSI)',
                'peran_kedudukan' => 'Wakil Ketua',
                'waktu_keanggotaan' => '2021 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subDays(1),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'nama_organisasi' => 'Ikatan Notaris Indonesia (INI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2016 - 2020',
                'tanggal_sinkron' => Carbon::now()->subDays(15),
                'status_pengajuan' => 'rejected',
                'created_at' => Carbon::now()->subMonths(5),
                'updated_at' => Carbon::now()->subDays(15),
            ],
            [
                'nama_organisasi' => 'Perhimpunan Advokat Indonesia (PERADI)',
                'peran_kedudukan' => 'Ketua Komisariat',
                'waktu_keanggotaan' => '2022 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subHours(12),
                'status_pengajuan' => 'pending',
                'created_at' => Carbon::now()->subWeeks(3),
                'updated_at' => Carbon::now()->subHours(12),
            ],
            [
                'nama_organisasi' => 'Ikatan Apoteker Indonesia (IAI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2019 - 2023',
                'tanggal_sinkron' => Carbon::now()->subDays(7),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(8),
                'updated_at' => Carbon::now()->subDays(7),
            ],
            [
                'nama_organisasi' => 'Persatuan Guru Republik Indonesia (PGRI)',
                'peran_kedudukan' => 'Koordinator Wilayah',
                'waktu_keanggotaan' => '2015 - Sekarang',
                'tanggal_sinkron' => null,
                'status_pengajuan' => 'draft',
                'created_at' => Carbon::now()->subWeeks(2),
                'updated_at' => Carbon::now()->subWeeks(2),
            ],
            [
                'nama_organisasi' => 'Asosiasi Dosen Indonesia (ADI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2020 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subDays(2),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(1),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            [
                'nama_organisasi' => 'Himpunan Kerukunan Tani Indonesia (HKTI)',
                'peran_kedudukan' => 'Pengurus Harian',
                'waktu_keanggotaan' => '2018 - 2021',
                'tanggal_sinkron' => Carbon::now()->subDays(20),
                'status_pengajuan' => 'rejected',
                'created_at' => Carbon::now()->subMonths(7),
                'updated_at' => Carbon::now()->subDays(20),
            ],
            [
                'nama_organisasi' => 'Ikatan Arsitek Indonesia (IAI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2021 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subHours(6),
                'status_pengajuan' => 'pending',
                'created_at' => Carbon::now()->subWeeks(6),
                'updated_at' => Carbon::now()->subHours(6),
            ],
            [
                'nama_organisasi' => 'Persatuan Wartawan Indonesia (PWI)',
                'peran_kedudukan' => 'Sekretaris Jenderal',
                'waktu_keanggotaan' => '2017 - 2022',
                'tanggal_sinkron' => Carbon::now()->subDays(8),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(9),
                'updated_at' => Carbon::now()->subDays(8),
            ],
            [
                'nama_organisasi' => 'Himpunan Ahli Konstruksi Indonesia (HAKI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2023 - Sekarang',
                'tanggal_sinkron' => null,
                'status_pengajuan' => 'draft',
                'created_at' => Carbon::now()->subWeeks(1),
                'updated_at' => Carbon::now()->subWeeks(1),
            ],
            [
                'nama_organisasi' => 'Ikatan Bidan Indonesia (IBI)',
                'peran_kedudukan' => 'Anggota Biasa',
                'waktu_keanggotaan' => '2020 - Sekarang',
                'tanggal_sinkron' => Carbon::now()->subDays(4),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(2),
                'updated_at' => Carbon::now()->subDays(4),
            ],
        ];

        foreach ($anggotaProfesi as $data) {
            AnggotaProfesi::create($data);
        }

        // Tambahkan beberapa data yang akan di-soft delete untuk testing
        $softDeletedData = [
            [
                'nama_organisasi' => 'Organisasi Test Hapus 1',
                'peran_kedudukan' => 'Test Anggota',
                'waktu_keanggotaan' => '2020 - 2022',
                'tanggal_sinkron' => Carbon::now()->subDays(30),
                'status_pengajuan' => 'approved',
                'created_at' => Carbon::now()->subMonths(3),
                'updated_at' => Carbon::now()->subDays(30),
                'deleted_at' => Carbon::now()->subDays(15),
            ],
            [
                'nama_organisasi' => 'Organisasi Test Hapus 2',
                'peran_kedudukan' => 'Test Pengurus',
                'waktu_keanggotaan' => '2019 - 2021',
                'tanggal_sinkron' => Carbon::now()->subDays(25),
                'status_pengajuan' => 'pending',
                'created_at' => Carbon::now()->subMonths(4),
                'updated_at' => Carbon::now()->subDays(25),
                'deleted_at' => Carbon::now()->subDays(10),
            ],
        ];

        foreach ($softDeletedData as $data) {
            AnggotaProfesi::create($data);
        }

        $this->command->info('AnggotaProfesi seeder completed!');
        $this->command->info('Created: 15 active records');
        $this->command->info('Created: 2 soft deleted records');
    }
}