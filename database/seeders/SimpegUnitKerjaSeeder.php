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
            ['kode_unit' => 'UKSI', 'nama_unit' => 'Unit Komputer dan Sistem Informasi Teknik Informatika', 'parent' => 'UIKA'],
            ['kode_unit' => 'PERPUS', 'nama_unit' => 'Perpustakaan', 'parent' => 'UIKA'],
            ['kode_unit' => 'ULBK', 'nama_unit' => 'Unit Layanan Bimbingan Konseling', 'parent' => 'UIKA'],
            ['kode_unit' => 'HUMAS', 'nama_unit' => 'Hubungan Masyarakat', 'parent' => 'UIKA'],
            ['kode_unit' => 'PARKIR', 'nama_unit' => 'Parkir', 'parent' => 'UIKA'],
            ['kode_unit' => 'SATPAM', 'nama_unit' => 'Satpam', 'parent' => 'UIKA'],
            ['kode_unit' => 'KOPERASI', 'nama_unit' => 'Koperasi', 'parent' => 'UIKA'],
            ['kode_unit' => 'UPB', 'nama_unit' => 'Unit Pelayanan Bahasa', 'parent' => 'UIKA'],
            ['kode_unit' => 'TP', 'nama_unit' => 'Techno Park', 'parent' => 'UIKA'],
            ['kode_unit' => 'TP', 'nama_unit' => 'Techno Park', 'parent' => 'UIKA'],
            ['kode_unit' => 'MKWK', 'nama_unit' => 'Mata Kuliah Wajib Kurikulum', 'parent' => 'UIKA'],
            ['kode_unit' => 'MKWK', 'nama_unit' => 'Mata Kuliah Wajib Kurikulum', 'parent' => 'UIKA'],
            ['kode_unit' => 'PSDM', 'nama_unit' => 'Unit Pengembangan Sumber Daya Mahasiswa', 'parent' => 'UIKA'],
            ['kode_unit' => 'UPLK', 'nama_unit' => 'Unit Pusat Layanan Kesehatan', 'parent' => 'UIKA'],
            ['kode_unit' => 'UMSARPRAS', 'nama_unit' => 'Unit Sarana dan Prasarana', 'parent' => 'UIKA'],
            ['kode_unit' => 'UPK', 'nama_unit' => 'Unit Pengembangan Karir', 'parent' => 'UIKA'],
            ['kode_unit' => 'UK', 'nama_unit' => 'Unit Kerjasama', 'parent' => 'UIKA'],
            

            // Lembaga di bawah LPPM
            
            // Lembaga di bawah PERPUS
            
            
            // Lembaga di bawah UIKA (asli uika euy)


            // Unit di bawah YPIKA (Yayasan Pendidikan Islam Ibn Khaldun)


            

            // Unit di bawah BAAK (Biro Akademik & Kemahasiswaan)
            ['kode_unit' => 'BAAK_AKADEMIK', 'nama_unit' => 'Direktorat Akademik', 'parent' => 'BAAK'],
            ['kode_unit' => 'BAAK_KEMAHASISWAAN', 'nama_unit' => 'Direktorat Kemahasiswaan', 'parent' => 'BAAK'],
            ['kode_unit' => 'ASPIKA', 'nama_unit' => 'Asrama dan Pelayanan Mahasiswa (ASPIKA)', 'parent' => 'BAAK'],

            // Unit di bawah BASK
            ['kode_unit' => 'BASK_UMUM_KEU_FAS', 'nama_unit' => 'Direktorat Umum, Keuangan & Fasilitas', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_TU', 'nama_unit' => 'Tata Usaha Pusat', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_KEU', 'nama_unit' => 'Bagian Keuangan', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_PA', 'nama_unit' => 'Unit Properti dan Aset', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_SEK', 'nama_unit' => 'Bagian Kesekretariatan', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_LAB', 'nama_unit' => 'Unit Laboratorium Terpadu', 'parent' => 'BASK'],
            ['kode_unit' => 'BASK_KEP', 'nama_unit' => 'Kepegawaian', 'parent' => 'BASK'],

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


            // Program Studi di bawah Fakultas Hukum (FH)
            ['kode_unit' => 'FH_IH', 'nama_unit' => 'Ilmu Hukum', 'parent' => 'FH'],

            // Program Studi di bawah Fakultas Ekonomi dan Bisnis (FEB)
            ['kode_unit' => 'FEB_PKD', 'nama_unit' => 'Perbankan dan Keuangan Digital', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_PI', 'nama_unit' => 'Perdagangan Internasional', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_BD', 'nama_unit' => 'Bisnis Digital', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_AK', 'nama_unit' => 'Akuntansi', 'parent' => 'FEB'],
            ['kode_unit' => 'FEB_MA', 'nama_unit' => 'Manajemen', 'parent' => 'FEB'],

            // Program Studi di bawah Fakultas Agama Islam (FAI)
            ['kode_unit' => 'FAI_BKPI', 'nama_unit' => 'Bimbingan dan Konseling Pendidikan Islam', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_PAI', 'nama_unit' => 'Pendidikan Agama Islam', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_AAS', 'nama_unit' => 'Ahwal Al Syakshiyah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_KPI', 'nama_unit' => 'Komunikasi dan Penyiaran Islam', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_ES', 'nama_unit' => 'Ekonomi Syariah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_PGMI', 'nama_unit' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_MHU', 'nama_unit' => 'Manajemen Haji dan Umrah', 'parent' => 'FAI'],
            ['kode_unit' => 'FAI_IAT', 'nama_unit' => 'Ilmu Al-Qu`an dan Tafsir', 'parent' => 'FAI'],

            // Program Studi di bawah Fakultas Teknik dan Sains (FT)
            ['kode_unit' => 'FT_IL', 'nama_unit' => 'Ilmu Lingkungan', 'parent' => 'FT'],
            ['kode_unit' => 'FT_SI', 'nama_unit' => 'Sistem Informasi', 'parent' => 'FT'],
            ['kode_unit' => 'FT_RPB', 'nama_unit' => 'Rekayasa Pertanian dan Biosistem', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TE', 'nama_unit' => 'Teknik Elektro', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TS', 'nama_unit' => 'Teknik Sipil', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TM', 'nama_unit' => 'Teknik Mesin', 'parent' => 'FT'],
            ['kode_unit' => 'FT_TI', 'nama_unit' => 'Teknik Informatika', 'parent' => 'FT'],
            
            // Program Studi di bawah Sekolah Pascasarjana (FPASCA)
            ['kode_unit' => 'FPASCA_MMA', 'nama_unit' => 'Manajemen (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MPI', 'nama_unit' => 'Pendidikan Agama Islam (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_HB', 'nama_unit' => 'Hukum Bisnis (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_DES', 'nama_unit' => 'Ekonomi Syariah (S3)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_DPI', 'nama_unit' => 'Pendidikan Agama Islam (S3)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MTP', 'nama_unit' => 'Teknologi Pendidikan (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MES', 'nama_unit' => 'Ekonomi Syariah (S2)', 'parent' => 'FPASCA'],
            ['kode_unit' => 'FPASCA_MKPI', 'nama_unit' => 'Komunikasi Penyiaran islam (S2)', 'parent' => 'FPASCA'],
            
            // Program Studi di bawah Fakultas Ilmu Kesehatan (FIKES)
            ['kode_unit' => 'FIKES_GZ', 'nama_unit' => 'Gizi', 'parent' => 'FIKES'],
            ['kode_unit' => 'FIKES_KM', 'nama_unit' => 'Kesehatan Masyarakat', 'parent' => 'FIKES'],

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
