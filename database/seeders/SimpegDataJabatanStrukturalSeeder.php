<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        // Mapping pegawai ke jabatan struktural berdasarkan NIP dan kode jabatan
        $assignments = [
            ['nip' => '196501011990031001', 'kode_jabatan' => '001'], // Rektor
            ['nip' => '198505152010121002', 'kode_jabatan' => '052'], // Dekan  
            ['nip' => '197803102005012001', 'kode_jabatan' => '053'], // Wakil Dekan Akademik
            ['nip' => '198201152008011001', 'kode_jabatan' => '054'], // Wakil Dekan Sumberdaya
            ['nip' => '198907122012012001', 'kode_jabatan' => '055'], // Wakil Dekan Kemahasiswaan
            ['nip' => '199001011015011001', 'kode_jabatan' => '056'], // Ketua Program Studi
            ['nip' => '199205102017012001', 'kode_jabatan' => '057'], // Sekretaris Program Studi
            ['nip' => '199306152018011001', 'kode_jabatan' => '058'], // Kepala Laboratorium
            ['nip' => '199408202019012001', 'kode_jabatan' => '060'], // Kepala Bagian Tata Usaha
        ];

        $data = [];
        foreach ($assignments as $assignment) {
            // Get pegawai ID
            $pegawaiId = DB::table('simpeg_pegawai')
                ->where('nip', $assignment['nip'])
                ->value('id');
                
            // Get jabatan struktural ID
            $jabatanId = DB::table('simpeg_jabatan_struktural')
                ->where('kode', $assignment['kode_jabatan'])
                ->value('id');

            if ($pegawaiId && $jabatanId) {
                $data[] = [
                    'pegawai_id' => $pegawaiId,
                    'jabatan_struktural_id' => $jabatanId,
                    'tgl_mulai' => '2023-01-01',
                    'tgl_selesai' => null, // Jabatan aktif
                    'no_sk' => 'SK/REKTOR/2023/' . str_pad(count($data) + 1, 3, '0', STR_PAD_LEFT),
                    'tgl_sk' => '2022-12-15',
                    'pejabat_penetap' => 'Rektor',
                    'file_jabatan' => 'sk_jabatan_' . $assignment['kode_jabatan'] . '.pdf',
                    'tgl_input' => $now->format('Y-m-d'),
                    'status_pengajuan' => 'disetujui',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($data)) {
            DB::table('simpeg_data_jabatan_struktural')->insert($data);
            $this->command->info('Berhasil assign ' . count($data) . ' pegawai ke jabatan struktural.');
        }
    }
}