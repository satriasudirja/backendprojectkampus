<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJenjangPendidikan;
// PERBAIKAN: Tambahkan DB facade untuk menggunakan Query Builder
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimpegUnitKerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        // Data unit kerja berdasarkan teks baru yang diberikan
        $data = [
            // Induk Utama
            ['kode_unit' => 'UIKA', 'nama_unit' => 'Universitas Ibn Khaldun', 'parent' => null],

           
            // Lembaga & Biro (Anak dari Universitas)
            ['kode_unit' => 'YPIKA', 'nama_unit' => 'Yayasan Pendiidikan Islam Ibn Khaldun', 'parent' => 'UIKA'],
            ['kode_unit' => 'BAAK', 'nama_unit' => 'Biro Administrasi Akademik dan Kemahasiswaan', 'parent' => 'UIKA'],
            ['kode_unit' => 'BASK', 'nama_unit' => 'Biro Administrasi Sumberdaya dan Kerjasama', 'parent' => 'UIKA'],
            ['kode_unit' => 'BPPSI', 'nama_unit' => 'Biro Perencanaan, Pelaporan dan Sistem Informasi', 'parent' => 'UIKA'],
            ['kode_unit' => 'KPMA', 'nama_unit' => 'Kantor Penjaminan Mutu dan Audit Internal', 'parent' => 'UIKA'],
            ['kode_unit' => 'LPPM', 'nama_unit' => 'Lembaga Penelitian dan Pengabdian Kepada Masyarakat', 'parent' => 'UIKA'],
            ['kode_unit' => 'BAUK', 'nama_unit' => 'Biro Administrasi Akademik dan Kemahasiswaan', 'parent' => 'UIKA'],
            ['kode_unit' => 'UKSI', 'nama_unit' => 'Unit Komputer dan Sistem Informasi Teknik Informatika', 'parent' => 'UIKA'],
            ['kode_unit' => 'PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'UIKA'],
            ['kode_unit' => 'BK', 'nama_unit' => 'Bimbingan Konseling', 'parent' => 'UIKA'],
            

            // Lembaga di bawah LPPM
            ['kode_unit' => 'LPPM_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'LPPM'],
            
            // Lembaga di bawah PERPUS
            ['kode_unit' => 'PERPUS_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'PERPUS'],
            
            
            // Lembaga di bawah UIKA (asli uika euy)
            ['kode_unit' => 'UIKA_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_KEU', 'nama_unit' => 'Keuangan', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_REK', 'nama_unit' => 'Rektorat', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_KK', 'nama_unit' => 'KEPEG KESEK', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_UKSI_TI', 'nama_unit' => 'Unit Komputer dan Sistem Informasi Teknik Informatika', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_HUM', 'nama_unit' => 'Hubungan Masyarakat', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_PAR', 'nama_unit' => 'Parkir', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_PAR', 'nama_unit' => 'Parkir', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_SEK', 'nama_unit' => 'Sekretariat Rektorat', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_KOP', 'nama_unit' => 'Koperasi UIKA', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_KEP', 'nama_unit' => 'Kepegawaian', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_KK', 'nama_unit' => 'KEPEG KESEK', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_AKA', 'nama_unit' => 'Akademik', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_PRO', 'nama_unit' => 'Properti', 'parent' => 'UIKA'],
            ['kode_unit' => 'UIKA_SAT', 'nama_unit' => 'Satpam', 'parent' => 'UIKA'],


            // Unit di bawah YPIKA (Yayasan Pendidikan Islam Ibn Khaldun)
            ['kode_unit' => 'YPIKA_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'YPIKA'],
            ['kode_unit' => 'YPIKA_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'YPIKA'],
            ['kode_unit' => 'YPIKA_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'YPIKA'],
            ['kode_unit' => 'YPIKA_SEK', 'nama_unit' => 'Sekretariat', 'parent' => 'YPIKA'],


            // Unit dibawah BAUK (Biro Administrasi Akademik dan Kemahasiswaan)
            ['kode_unit' => 'BAUK_KEMAHASISWAAN', 'nama_unit' => 'Direktorat Kemahasiswaan', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_PERPUS', 'nama_unit' => 'Unit Perpustakaan', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_REK', 'nama_unit' => 'Rektorat', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_LPPM', 'nama_unit' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_UKSI_TI', 'nama_unit' => 'Unit Komputer dan Sistem Informasi Teknik Informatika', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_PELAPORAN', 'nama_unit' => 'Pelaporan', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_UIKA', 'nama_unit' => 'Universitas Ibn Khaldun', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_HUM', 'nama_unit' => 'Hubungan Masyarakat', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_AKA', 'nama_unit' => 'Akademik', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_SAT', 'nama_unit' => 'Satpam', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_KEU', 'nama_unit' => 'Keuangan', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_SEK', 'nama_unit' => 'Sekretariat', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_KOP', 'nama_unit' => 'Koperasi UIKA', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_KEP', 'nama_unit' => 'Kepegawaian', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_PAR', 'nama_unit' => 'Parkir', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_PRO', 'nama_unit' => 'Properti', 'parent' => 'BAUK'],
            ['kode_unit' => 'BAUK_KEPEG_KESEK', 'nama_unit' => 'KEPEG KESEK', 'parent' => 'BAUK'],

            // Unit di bawah BAAK (Biro Akademik & Kemahasiswaan)
            ['kode_unit' => 'BAAK_AKADEMIK', 'nama_unit' => 'Direktorat Akademik', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_KEMAHASISWAAN', 'nama_unit' => 'Direktorat Kemahasiswaan', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_PERPUS', 'nama_unit' => 'Unit Perpustakaan', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_REK', 'nama_unit' => 'Rektorat', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_LPPM', 'nama_unit' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_UKSI_TI', 'nama_unit' => 'Unit Komputer dan Sistem Informasi Teknik Informatika', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_PELAPORAN', 'nama_unit' => 'Pelaporan', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_UIKA', 'nama_unit' => 'Universitas Ibn Khaldun', 'parent' => 'BAAK'],


            ['kode_unit' => 'ASPIKA', 'nama_unit' => 'Asrama dan Pelayanan Mahasiswa (ASPIKA)', 'parent' => 'BAAK'],

            // Bawahannya ASPIKA
            ['kode_unit' => 'ASPIKA_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'ASPIKA'],
            ['kode_unit' => 'ASPIKA_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'ASPIKA'],
            ['kode_unit' => 'ASPIKA_A', 'nama_unit' => 'ASPIKA', 'parent' => 'ASPIKA'],
            ['kode_unit' => 'ASPIKA_SAT', 'nama_unit' => 'Satpam', 'parent' => 'ASPIKA'],
            ['kode_unit' => 'ASPIKA_PEN', 'nama_unit' => 'Pendidikan', 'parent' => 'ASPIKA'],

            
            // Unit di bawah BASK
            ['kode_unit' => 'BASK_UMUM_KEU_FAS', 'nama_unit' => 'Direktorat Umum, Keuangan & Fasilitas', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_KEP', 'nama_unit' => 'Direktorat Kepegawaian', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_HUM', 'nama_unit' => 'Hubungan Masyarakat', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_TU', 'nama_unit' => 'Tata Usaha Pusat', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_KEU', 'nama_unit' => 'Bagian Keuangan', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_RT', 'nama_unit' => 'Bagian Rumah Tangga', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_SAT', 'nama_unit' => 'Satpam', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_PAR', 'nama_unit' => 'Unit Pengelola Parkir', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_PA', 'nama_unit' => 'Unit Properti dan Aset', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_SEK', 'nama_unit' => 'Bagian Kesekretariatan', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_LAB', 'nama_unit' => 'Unit Laboratorium Terpadu', 'parent' => 'BASK'],

              // Unit di bawah BPPSI (Biro Perencanaan, Pelaporan & SI)
            ['kode_unit' => 'BPPSI_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'BPPSI'],
            ['kode_unit' => 'BPPSI_UKSI_TI', 'nama_unit' => 'Unit Kerja Sistem Informasi & Teknologi', 'parent' => 'BPPSI'],
            

            // Fakultas (Anak dari Universitas)
            ['kode_unit' => 'FKIP', 'nama_unit' => 'Fakultas Keguruan dan Ilmu Pendidikan', 'parent' => 'UIKA'],
            ['kode_unit' => 'FH', 'nama_unit' => 'Fakultas Hukum', 'parent' => 'UIKA'],
            ['kode_unit' => 'FEB', 'nama_unit' => 'Fakultas Ekonomi dan Bisnis', 'parent' => 'UIKA'],
            ['kode_unit' => 'FAI', 'nama_unit' => 'Fakultas Agama Islam', 'parent' => 'UIKA'],
            ['kode_unit' => 'FT', 'nama_unit' => 'Fakultas Teknik dan Sains', 'parent' => 'UIKA'],
            ['kode_unit' => 'FPASCA', 'nama_unit' => 'Sekolah Pascasarjana', 'parent' => 'UIKA'],
            ['kode_unit' => 'FIKES', 'nama_unit' => 'Fakultas Ilmu Kesehatan', 'parent' => 'UIKA'],

            // Program Studi di bawah Fakultas Keguruan dan Ilmu Pendidikan (FKIP)
            ['kode_unit' => 'FKIP_PVDF', 'nama_unit' => 'Pendidikan Vokasional Desain Fashion', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_PM', 'nama_unit' => 'Pendidikan Matematika', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_TP', 'nama_unit' => 'Teknologi Pendidikan', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_PM', 'nama_unit' => 'Pendidikan Masyarakat', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_PPG', 'nama_unit' => 'Pendidikan Profesi Guru', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_PBI', 'nama_unit' => 'Pendidikan Bahasa Inggris', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_PLS', 'nama_unit' => 'Pendidikan Luar Sekolah', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_LAB', 'nama_unit' => 'Laboran', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_PERPUS', 'nama_unit' => 'Perpusatakaan', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_KEU', 'nama_unit' => 'Keuangan', 'parent' => 'FKIP'],
            ['kode_unit' => 'FKIP_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FKIP'],


            // Program Studi di bawah Fakultas Hukum (FH)
            ['kode_unit' => 'FH_IH', 'nama_unit' => 'Ilmu Hukum', 'parent' => 'FH'],
            ['kode_unit' => 'FH_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FH'],
            ['kode_unit' => 'FH_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FH'],
            ['kode_unit' => 'FH_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FH'],
            ['kode_unit' => 'FH_PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'FH'],
            ['kode_unit' => 'FH_PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'FH'],
            ['kode_unit' => 'FH_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FH'],

            // Program Studi di bawah Fakultas Ekonomi dan Bisnis (FEB)
            ['kode_unit' => 'FEB_PKD', 'nama_unit' => 'Perbankan dan Keuangan Digital', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_PI', 'nama_unit' => 'Perdagangan Internasional', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_BD', 'nama_unit' => 'Bisnis Digital', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_AK', 'nama_unit' => 'Akuntansi', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_MA', 'nama_unit' => 'Manajemen', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FEB'],

            // Program Studi di bawah Fakultas Agama Islam (FAI)
            ['kode_unit' => 'FAI_BKPI', 'nama_unit' => 'Bimbingan dan Konseling Pendidikan Islam', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_PAI', 'nama_unit' => 'Pendidikan Agama Islam', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_AAS', 'nama_unit' => 'Ahwal Al Syakshiyah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_KPI', 'nama_unit' => 'Komunikasi dan Penyiaran Islam', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_ES', 'nama_unit' => 'Ekonomi Syariah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_PGMI', 'nama_unit' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_MHU', 'nama_unit' => 'Manajemen Haji dan Umrah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_IAT', 'nama_unit' => 'Ilmu Al-Qu`an dan Tafsir', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_HUM', 'nama_unit' => 'Hubungan Masyarakat', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FAI'],

            // Program Studi di bawah Fakultas Teknik dan Sains (FT)
            ['kode_unit' => 'FT_IL', 'nama_unit' => 'Ilmu Lingkungan', 'parent' => 'FT'],
            ['kode_unit' => 'FT_SI', 'nama_unit' => 'Sistem Informasi', 'parent' => 'FT'],
            ['kode_unit' => 'FT_RPB', 'nama_unit' => 'Rekayasa Pertanian dan Biosistem', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TE', 'nama_unit' => 'Teknik Elektro', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TS', 'nama_unit' => 'Teknik Sipil', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TM', 'nama_unit' => 'Teknik Mesin', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TI', 'nama_unit' => 'Teknik Informatika', 'parent' => 'FT'],
            ['kode_unit' => 'FT_LAB', 'nama_unit' => 'Laboran', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FT'],
            ['kode_unit' => 'FT_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FT'],
            ['kode_unit' => 'FT_KEU', 'nama_unit' => 'Keuangan', 'parent' => 'FT'],
            ['kode_unit' => 'FT_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FT'],
            ['kode_unit' => 'FT_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FT'],
            
            // Program Studi di bawah Sekolah Pascasarjana (FPASCA)
            ['kode_unit' => 'FPASCA_MMA', 'nama_unit' => 'Manajemen (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MPI', 'nama_unit' => 'Pendidikan Agama Islam (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_HB', 'nama_unit' => 'Hukum Bisnis (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_DES', 'nama_unit' => 'Ekonomi Syariah (S3)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_DPI', 'nama_unit' => 'Pendidikan Agama Islam (S3)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MTP', 'nama_unit' => 'Teknologi Pendidikan (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MES', 'nama_unit' => 'Ekonomi Syariah (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MKPI', 'nama_unit' => 'Komunikasi Penyiaran islam (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_PERPUS', 'nama_unit' => 'Perpusatakaan', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FPASCA'],
            
            // Program Studi di bawah Fakultas Ilmu Kesehatan (FIKES)
            ['kode_unit' => 'FIKES_GZ', 'nama_unit' => 'Gizi', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_KM', 'nama_unit' => 'Kesehatan Masyarakat', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_KEU', 'nama_unit' => 'Keuangan', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_TU', 'nama_unit' => 'Tata Usaha', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_RT', 'nama_unit' => 'Rumah Tangga', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_LAB', 'nama_unit' => 'Laboran', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_AKA', 'nama_unit' => 'Akademik', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_UMUM_KEU_FAS', 'nama_unit' => 'UMUM KEU FAS', 'parent' => 'FIKES'],

            // Unit Lain-lain (Anak dari Universitas)
            ['kode_unit' => 'KP', 'nama_unit' => 'Keuangan Dan Perbankan', 'parent' => 'UIKA'],
            ['kode_unit' => 'BAU', 'nama_unit' => 'Bank Amanah Ummah', 'parent' => 'UIKA'],
        ];

        // PERBAIKAN: Gunakan DB::table() jika Model tidak ada
        // Pastikan seeder untuk tabel-tabel ini sudah dijalankan sebelumnya.



        $jenjangPendidikanIds = SimpegJenjangPendidikan::pluck('id');

        foreach ($data as $unit) {
            // Mencari parent_id berdasarkan kode_unit parent
            $parent = null;
            if ($unit['parent']) {
                $parentData = SimpegUnitKerja::where('kode_unit', $unit['parent'])->first();
                if ($parentData) {
                    $parent = $parentData->id;
                }
            }

            SimpegUnitKerja::updateOrCreate(
                ['kode_unit' => $unit['kode_unit']], // Kunci untuk mencari data
                [ // Data untuk di-create atau di-update
                    'nama_unit' => $unit['nama_unit'],
                    'parent_unit_id' => $parent,
                    // Ambil ID UUID acak dari data yang sudah ada
                    'tk_pendidikan_id' => $jenjangPendidikanIds->isNotEmpty()? $jenjangPendidikanIds->random() : null,
                    'alamat' => $faker->address,
                    'telepon' => $faker->phoneNumber,
                    'website' => $faker->url,
                    'alamat_email' => $faker->unique()->safeEmail,
                    'no_sk_akreditasi' => strtoupper(Str::random(10)),
                    'tanggal_akreditasi' => $faker->date(),
                    'no_sk_pendirian' => strtoupper(Str::random(10)),
                    'tanggal_sk_pendirian' => $faker->date(),
                    'gedung' => 'Gedung ' . strtoupper(Str::random(1)),
                ]
            );
        }
    }
}
