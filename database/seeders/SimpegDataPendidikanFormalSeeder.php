<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenjangPendidikan;
use App\Models\MasterPerguruanTinggi;
use App\Models\MasterProdiPerguruanTinggi;
use App\Models\MasterGelarAkademik;
use Carbon\Carbon;

class SimpegDataPendidikanFormalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Make sure there are related records in other tables
        $pegawaiIds = SimpegPegawai::pluck('id')->toArray();
        $jenjangPendidikanIds = SimpegJenjangPendidikan::pluck('id')->toArray();
        $perguruanTinggiIds = MasterPerguruanTinggi::pluck('id')->toArray();
        $prodiIds = MasterProdiPerguruanTinggi::pluck('id')->toArray();
        $gelarIds = MasterGelarAkademik::pluck('id')->toArray();
        
        // If no test data exists, create dummy IDs for testing
        if (empty($pegawaiIds)) {
            $pegawaiIds = [1, 2, 3];
        }
        
        if (empty($jenjangPendidikanIds)) {
            $jenjangPendidikanIds = [1, 2, 3, 4]; // S1, S2, S3, D3
        }
        
        if (empty($perguruanTinggiIds)) {
            $perguruanTinggiIds = [1, 2, 3, 4, 5];
        }
        
        if (empty($prodiIds)) {
            $prodiIds = [1, 2, 3, 4, 5];
        }
        
        if (empty($gelarIds)) {
            $gelarIds = [1, 2, 3, 4, 5];
        }
        
        $statusOptions = ['draft', 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan'];
        $universitas = [
            'Universitas Indonesia',
            'Institut Teknologi Bandung',
            'Universitas Gadjah Mada',
            'Universitas Padjadjaran',
            'Institut Pertanian Bogor',
            'Universitas Airlangga',
            'Universitas Diponegoro',
            'Universitas Brawijaya'
        ];
        $bidangStudi = [
            'Teknik Informatika',
            'Kedokteran',
            'Ilmu Hukum',
            'Akuntansi',
            'Manajemen',
            'Sistem Informasi',
            'Teknik Elektro',
            'Sastra Indonesia',
            'Ilmu Komunikasi',
            'Psikologi'
        ];
        
        // Create 50 education records
        for ($i = 0; $i < 50; $i++) {
            $pegawaiId = $pegawaiIds[array_rand($pegawaiIds)];
            $jenjangId = $jenjangPendidikanIds[array_rand($jenjangPendidikanIds)];
            $tahunLulus = rand(2000, 2023);
            $tahunMasuk = $tahunLulus - rand(3, 5);
            $status = $statusOptions[array_rand($statusOptions)];
            
            $pendidikan = [
                'pegawai_id' => $pegawaiId,
                'lokasi_studi' => rand(0, 1) ? 'Dalam Negeri' : 'Luar Negeri',
                'jenjang_pendidikan_id' => $jenjangId,
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
                'jumlah_semster_ditempuh' => rand(6, 14),
                'jumlah_sks_kelulusan' => rand(120, 160),
                'ipk_kelulusan' => rand(275, 400) / 100, // 2.75 - 4.00
                'status_pengajuan' => $status,
            ];
            
            // Add dates based on status
            if (in_array($status, ['diajukan', 'disetujui', 'ditolak', 'ditangguhkan'])) {
                $pendidikan['tanggal_diajukan'] = Carbon::now()->subDays(rand(10, 90));
                
                if (in_array($status, ['disetujui', 'ditolak', 'ditangguhkan'])) {
                    $pendidikan['tanggal_disetujui'] = Carbon::parse($pendidikan['tanggal_diajukan'])->addDays(rand(1, 14));
                }
            }
            
            SimpegDataPendidikanFormal::create($pendidikan);
        }
    }
}