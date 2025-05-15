<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataJabatanFungsionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dapatkan ID jabatan fungsional yang valid dari database
        $validJabatanIds = DB::table('simpeg_jabatan_fungsional')
            ->pluck('id')
            ->toArray();

        if (empty($validJabatanIds)) {
            $this->command->error('Tabel simpeg_jabatan_fungsional kosong. Jalankan SimpegJabatanFungsionalSeeder terlebih dahulu.');
            return;
        }

        $pejabatList = [
            'Prof. Dr. Ahmad Rivai, M.Pd.',
            'Dr. Budi Santoso, M.Si.',
            'Prof. Dr. Siti Nurhayati, M.Hum.',
            'Dr. Darmawan, M.Sc.',
            'Prof. Dr. Ratna Dewi, M.Ed.',
        ];

        $statusList = ['draft', 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan'];

        // Seed 25 records (jumlah diperkecil untuk mengurangi kemungkinan error)
        $data = [];
        for ($i = 1; $i <= 25; $i++) {
            $pegawaiId = rand(1, 100);
            // Pastikan hanya menggunakan ID yang valid
            $jabatanFungsionalId = $validJabatanIds[array_rand($validJabatanIds)];
            
            $tmtJabatan = Carbon::now()->subMonths(rand(1, 60))->format('Y-m-d');
            $tanggalSk = Carbon::parse($tmtJabatan)->subDays(rand(30, 90))->format('Y-m-d');
            
            $data[] = [
                'jabatan_fungsional_id' => $jabatanFungsionalId,
                'pegawai_id' => $pegawaiId,
                'tmt_jabatan' => $tmtJabatan,
                'pejabat_penetap' => $pejabatList[array_rand($pejabatList)],
                'no_sk' => 'SK/JF/' . rand(1000, 9999) . '/' . date('Y'),
                'tanggal_sk' => $tanggalSk,
                'file_sk_jabatan' => null,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
                'status_pengajuan' => $statusList[array_rand($statusList)],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // Pastikan data khusus untuk pegawai 20 dan 81 juga menggunakan ID valid
        if (!empty($validJabatanIds)) {
            // Tambahkan data khusus untuk pegawai ID 20
            $data[] = [
                'jabatan_fungsional_id' => $validJabatanIds[0], // Gunakan ID pertama yang valid
                'pegawai_id' => 20, // Harsanto Firgantoro
                'tmt_jabatan' => '2022-03-01',
                'pejabat_penetap' => 'Prof. Dr. Sukarno, M.Pd.',
                'no_sk' => 'SK/JF/2022/003',
                'tanggal_sk' => '2022-02-15',
                'file_sk_jabatan' => null,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
                'status_pengajuan' => 'disetujui',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            // Tambahkan data khusus untuk pegawai ID 81
            $data[] = [
                'jabatan_fungsional_id' => $validJabatanIds[count($validJabatanIds) > 1 ? 1 : 0], // Gunakan ID kedua jika ada
                'pegawai_id' => 81, // Gabriella Elma Susanti
                'tmt_jabatan' => '2021-09-01',
                'pejabat_penetap' => 'Dr. Ratna Megawati, M.Si.',
                'no_sk' => 'SK/JF/2021/112',
                'tanggal_sk' => '2021-08-10',
                'file_sk_jabatan' => null,
                'tgl_input' => Carbon::now()->format('Y-m-d'),
                'status_pengajuan' => 'disetujui',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('simpeg_data_jabatan_fungsional')->insert($data);
    }
}