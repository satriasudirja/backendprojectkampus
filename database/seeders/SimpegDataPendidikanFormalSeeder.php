<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpegDataPendidikanFormalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil semua UUID dari tabel relasi
        $pegawaiIds = DB::table('simpeg_pegawai')->pluck('id')->toArray();
        $jenjangPendidikanIds = DB::table('simpeg_jenjang_pendidikan')->pluck('id')->toArray();
        $perguruanTinggiIds = DB::table('simpeg_master_perguruan_tinggi')->pluck('id')->toArray();
        $prodiIds = DB::table('simpeg_master_prodi_perguruan_tinggi')->pluck('id')->toArray();
        $gelarIds = DB::table('simpeg_master_gelar_akademik')->pluck('id')->toArray();

        // Validasi data referensi
        if (empty($pegawaiIds) || empty($jenjangPendidikanIds) || empty($perguruanTinggiIds) || empty($prodiIds) || empty($gelarIds)) {
            $this->command->error('Satu atau lebih tabel relasi (pegawai, jenjang, PT, prodi, gelar) kosong.');
            $this->command->info('Harap jalankan seeder untuk tabel-tabel tersebut terlebih dahulu sebelum menjalankan seeder ini.');
            return;
        }
        
        $statusOptions = ['draft', 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan'];
        $bidangStudi = [
            'Teknik Informatika', 'Kedokteran', 'Ilmu Hukum', 'Akuntansi', 'Manajemen',
            'Sistem Informasi', 'Teknik Elektro', 'Sastra Indonesia', 'Ilmu Komunikasi', 'Psikologi'
        ];
        
        $dataPendidikan = [];

        // Buat 50 data pendidikan
        for ($i = 0; $i < 50; $i++) {
            $tahunLulus = rand(2000, 2023);
            $tahunMasuk = $tahunLulus - rand(3, 5);
            $status = $statusOptions[array_rand($statusOptions)];
            
            $pendidikan = [
                'id' => Str::uuid(),
                'pegawai_id' => $pegawaiIds[array_rand($pegawaiIds)],
                'lokasi_studi' => rand(0, 1) ? 'Dalam Negeri' : 'Luar Negeri',
                'jenjang_pendidikan_id' => $jenjangPendidikanIds[array_rand($jenjangPendidikanIds)],
                'perguruan_tinggi_id' => $perguruanTinggiIds[array_rand($perguruanTinggiIds)],
                'prodi_perguruan_tinggi_id' => $prodiIds[array_rand($prodiIds)],
                'gelar_akademik_id' => $gelarIds[array_rand($gelarIds)],
                'bidang_studi' => $bidangStudi[array_rand($bidangStudi)],
                'nisn' => 'NISN' . rand(10000000, 99999999),
                'konsentrasi' => 'Konsentrasi ' . rand(1, 5),
                'tahun_masuk' => (string)$tahunMasuk,
                'tanggal_kelulusan' => Carbon::createFromDate($tahunLulus, rand(1, 12), rand(1, 28)),
                'tahun_lulus' => (string)$tahunLulus,
                'nomor_ijazah' => 'IJZ-' . rand(100000, 999999) . '/' . $tahunLulus,
                'tanggal_ijazah' => Carbon::createFromDate($tahunLulus, rand(1, 12), rand(1, 28)),
                'nomor_ijazah_negara' => 'NI-' . rand(100000, 999999) . '/' . $tahunLulus,
                'gelar_ijazah_negara' => 'S.Kom',
                'tgl_input' => Carbon::now(),
                'tanggal_ijazah_negara' => Carbon::createFromDate($tahunLulus, rand(1, 12), rand(1, 28)),
                'nomor_induk' => 'NIM' . rand(100000, 999999),
                'judul_tugas' => 'Analisis dan Implementasi ' . $bidangStudi[array_rand($bidangStudi)],
                'letak_gelar' => rand(0, 1) ? 'depan' : 'belakang',
                'jumlah_semester_ditempuh' => rand(6, 14),
                'jumlah_sks_kelulusan' => rand(120, 160),
                'ipk_kelulusan' => rand(275, 400) / 100,
                'status_pengajuan' => $status,
                'created_at' => now(),
                'updated_at' => now(),

                // --- SOLUSI: Definisikan kolom di awal dengan nilai null ---
                'tanggal_diajukan' => null,
                'tanggal_disetujui' => null,
            ];
            
            // Isi tanggal hanya jika statusnya sesuai
            if (in_array($status, ['diajukan', 'disetujui', 'ditolak', 'ditangguhkan'])) {
                $pendidikan['tanggal_diajukan'] = Carbon::now()->subDays(rand(10, 90));
                
                if (in_array($status, ['disetujui', 'ditolak', 'ditangguhkan'])) {
                    $pendidikan['tanggal_disetujui'] = Carbon::parse($pendidikan['tanggal_diajukan'])->addDays(rand(1, 14));
                }
            }
            
            $dataPendidikan[] = $pendidikan;
        }
        
        // Gunakan bulk insert untuk efisiensi
        DB::table('simpeg_data_pendidikan_formal')->insert($dataPendidikan);
    }
}
