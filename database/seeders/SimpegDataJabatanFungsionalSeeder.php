<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

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

        // FIXED: Dapatkan ID pegawai yang valid dari database
        $validPegawaiIds = DB::table('simpeg_pegawai')
            ->pluck('id')
            ->toArray();

        if (empty($validPegawaiIds)) {
            $this->command->error('Tabel simpeg_pegawai kosong. Jalankan SimpegPegawaiSeeder terlebih dahulu.');
            return;
        }

        $this->command->info('Found ' . count($validPegawaiIds) . ' valid pegawai IDs: ' . implode(', ', array_slice($validPegawaiIds, 0, 10)) . (count($validPegawaiIds) > 10 ? '...' : ''));

        $pejabatList = [
            'Prof. Dr. Ahmad Rivai, M.Pd.',
            'Dr. Budi Santoso, M.Si.',
            'Prof. Dr. Siti Nurhayati, M.Hum.',
            'Dr. Darmawan, M.Sc.',
            'Prof. Dr. Ratna Dewi, M.Ed.',
        ];

        $statusList = ['draft', 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan'];

        // Seed records - sesuaikan jumlah dengan pegawai yang tersedia
        $numberOfRecords = min(25, count($validPegawaiIds)); // Tidak lebih dari jumlah pegawai yang ada
        $data = [];
        
        // Shuffle pegawai IDs untuk variasi
        $shuffledPegawaiIds = $validPegawaiIds;
        shuffle($shuffledPegawaiIds);

        for ($i = 0; $i < $numberOfRecords; $i++) {
            // FIXED: Gunakan hanya pegawai ID yang valid
            $pegawaiId = $shuffledPegawaiIds[$i % count($validPegawaiIds)];
            
            // Pastikan hanya menggunakan jabatan fungsional ID yang valid
            $jabatanFungsionalId = $validJabatanIds[array_rand($validJabatanIds)];
            
            $tmtJabatan = Carbon::now()->subMonths(rand(1, 60))->format('Y-m-d');
            $tanggalSk = Carbon::parse($tmtJabatan)->subDays(rand(30, 90))->format('Y-m-d');
            
            $data[] = [
                'id' => Str::uuid(),
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

        // FIXED: Tambahkan data khusus hanya jika pegawai ID tersebut benar-benar ada
        if (in_array(20, $validPegawaiIds)) {
            $data[] = [
                
                'id' => Str::uuid(),
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
        } else {
            $this->command->warn('Pegawai ID 20 tidak ditemukan, melewati data khusus untuk Harsanto Firgantoro');
        }

        if (in_array(81, $validPegawaiIds)) {
            $data[] = [
                
                'id' => Str::uuid(),
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
        } else {
            $this->command->warn('Pegawai ID 81 tidak ditemukan, melewati data khusus untuk Gabriella Elma Susanti');
        }

        try {
            DB::table('simpeg_data_jabatan_fungsional')->insert($data);
            $this->command->info('Successfully seeded ' . count($data) . ' jabatan fungsional records');
        } catch (\Exception $e) {
            $this->command->error('Failed to seed jabatan fungsional data: ' . $e->getMessage());
            
            // Debug: Show some sample data
            $this->command->info('Sample data structure:');
            if (!empty($data)) {
                $this->command->line(json_encode($data[0], JSON_PRETTY_PRINT));
            }
        }
    }
}