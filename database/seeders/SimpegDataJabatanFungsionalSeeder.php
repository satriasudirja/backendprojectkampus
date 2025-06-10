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
        $validJabatanIds = DB::table('simpeg_jabatan_fungsional')->pluck('id')->toArray();

        if (empty($validJabatanIds)) {
            $this->command->error('Tabel simpeg_jabatan_fungsional kosong. Jalankan SimpegJabatanFungsionalSeeder terlebih dahulu.');
            return;
        }

        $pejabatList = [
            'Prof. Dr. Ahmad Rivai, M.Pd.', 'Dr. Budi Santoso, M.Si.', 'Prof. Dr. Siti Nurhayati, M.Hum.',
            'Dr. Darmawan, M.Sc.', 'Prof. Dr. Ratna Dewi, M.Ed.',
        ];

        $statusList = ['draft', 'diajukan', 'disetujui', 'ditolak']; // Dihapus 'ditangguhkan' karena tidak ada di controller
        $data = [];

        for ($i = 1; $i <= 25; $i++) {
            $pegawaiId = rand(1, 100);
            $jabatanFungsionalId = $validJabatanIds[array_rand($validJabatanIds)];
            
            $tmtJabatan = Carbon::now()->subMonths(rand(1, 60));
            $tanggalSk = $tmtJabatan->copy()->subDays(rand(30, 90));
            $status = $statusList[array_rand($statusList)];

            $item = [
                'jabatan_fungsional_id' => $jabatanFungsionalId,
                'pegawai_id' => $pegawaiId,
                'tmt_jabatan' => $tmtJabatan->format('Y-m-d'),
                'pejabat_penetap' => $pejabatList[array_rand($pejabatList)],
                'no_sk' => 'SK/JF/' . rand(1000, 9999) . '/' . date('Y'),
                'tanggal_sk' => $tanggalSk->format('Y-m-d'),
                'file_sk_jabatan' => null,
                'tgl_input' => Carbon::now()->subDays(rand(10, 30))->format('Y-m-d'),
                'status_pengajuan' => $status,
                'tgl_diajukan' => null,
                'tgl_disetujui' => null,
                'tgl_ditolak' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            // Menambahkan tanggal berdasarkan status
            $tglDiajukan = Carbon::now()->subDays(rand(5, 10));
            switch ($status) {
                case 'diajukan':
                    $item['tgl_diajukan'] = $tglDiajukan;
                    break;
                case 'disetujui':
                    $item['tgl_diajukan'] = $tglDiajukan;
                    $item['tgl_disetujui'] = $tglDiajukan->copy()->addDays(rand(1, 4));
                    break;
                case 'ditolak':
                    $item['tgl_diajukan'] = $tglDiajukan;
                    $item['tgl_ditolak'] = $tglDiajukan->copy()->addDays(rand(1, 4));
                    break;
            }
            $data[] = $item;
        }

        // Data khusus untuk pegawai 20
        $data[] = [
            'jabatan_fungsional_id' => $validJabatanIds[0],
            'pegawai_id' => 20,
            'tmt_jabatan' => '2022-03-01',
            'pejabat_penetap' => 'Prof. Dr. Sukarno, M.Pd.',
            'no_sk' => 'SK/JF/2022/003',
            'tanggal_sk' => '2022-02-15',
            'file_sk_jabatan' => null,
            'tgl_input' => '2022-02-10',
            'status_pengajuan' => 'disetujui',
            'tgl_diajukan' => '2022-02-20 10:00:00',
            'tgl_disetujui' => '2022-02-25 14:30:00',
            'tgl_ditolak' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Data khusus untuk pegawai 81
        $data[] = [
            'jabatan_fungsional_id' => $validJabatanIds[count($validJabatanIds) > 1 ? 1 : 0],
            'pegawai_id' => 81,
            'tmt_jabatan' => '2021-09-01',
            'pejabat_penetap' => 'Dr. Ratna Megawati, M.Si.',
            'no_sk' => 'SK/JF/2021/112',
            'tanggal_sk' => '2021-08-10',
            'file_sk_jabatan' => null,
            'tgl_input' => '2021-08-12',
            'status_pengajuan' => 'disetujui',
            'tgl_diajukan' => '2021-08-15 09:00:00',
            'tgl_disetujui' => '2021-08-20 11:00:00',
            'tgl_ditolak' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        DB::table('simpeg_data_jabatan_fungsional')->insert($data);
    }
}