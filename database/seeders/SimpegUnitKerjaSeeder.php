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

            // Unit di bawah Rektorat
            ['kode_unit' => 'SEK_REKTORAT', 'nama_unit' => 'Sekretariat Rektorat', 'parent' => 'UIKA'],
            ['kode_unit' => 'KOPERASI_UIKA', 'nama_unit' => 'Koperasi UIKA', 'parent' => 'UIKA'],

            // Unit di bawah BAAK (Biro Akademik & Kemahasiswaan)
            ['kode_unit' => 'AKADEMIK', 'nama_unit' => 'Direktorat Akademik', 'parent' => 'BAAK'],
            ['kode_unit' => 'KEMAHASISWAAN', 'nama_unit' => 'Direktorat Kemahasiswaan', 'parent' => 'BAAK'],
            ['kode_unit' => 'PERPUS', 'nama_unit' => 'Unit Perpustakaan', 'parent' => 'BAAK'],
            ['kode_unit' => 'ASPIKA', 'nama_unit' => 'Asrama dan Pelayanan Mahasiswa (ASPIKA)', 'parent' => 'BAAK'],
            
            // Unit di bawah BASK
            ['kode_unit' => 'UMUM_KEU_FAS', 'nama_unit' => 'Direktorat Umum, Keuangan & Fasilitas', 'parent' => 'BASK'],
            ['kode_unit' => 'KEPEGAWAIAN', 'nama_unit' => 'Direktorat Kepegawaian', 'parent' => 'BASK'],
            ['kode_unit' => 'HUMAS', 'nama_unit' => 'Hubungan Masyarakat', 'parent' => 'BASK'],
            ['kode_unit' => 'TATA_USAHA', 'nama_unit' => 'Tata Usaha Pusat', 'parent' => 'BASK'],
            ['kode_unit' => 'KEUANGAN', 'nama_unit' => 'Bagian Keuangan', 'parent' => 'BASK'],
            ['kode_unit' => 'RUMAH_TANGGA', 'nama_unit' => 'Bagian Rumah Tangga', 'parent' => 'BASK'],
            ['kode_unit' => 'SATPAM', 'nama_unit' => 'Satuan Pengamanan', 'parent' => 'BASK'],
            ['kode_unit' => 'PARKIR', 'nama_unit' => 'Unit Pengelola Parkir', 'parent' => 'BASK'],
            ['kode_unit' => 'PROPERTI', 'nama_unit' => 'Unit Properti dan Aset', 'parent' => 'BASK'],
            ['kode_unit' => 'KESEKRETARIATAN', 'nama_unit' => 'Bagian Kesekretariatan', 'parent' => 'BASK'],
            ['kode_unit' => 'LABORAN', 'nama_unit' => 'Unit Laboratorium Terpadu', 'parent' => 'BASK'],

              // Unit di bawah BPPSI (Biro Perencanaan, Pelaporan & SI)
            ['kode_unit' => 'PELAPORAN', 'nama_unit' => 'Bagian Pelaporan', 'parent' => 'BPPSI'],
            ['kode_unit' => 'UKSI_TI', 'nama_unit' => 'Unit Kerja Sistem Informasi & Teknologi', 'parent' => 'BPPSI'],
            

            // Fakultas (Anak dari Universitas)
            ['kode_unit' => 'FKIP', 'nama_unit' => 'Fakultas Keguruan dan Ilmu Pendidikan', 'parent' => 'UIKA'],
            ['kode_unit' => 'FH', 'nama_unit' => 'Fakultas Hukum', 'parent' => 'UIKA'],
            ['kode_unit' => 'FEB', 'nama_unit' => 'Fakultas Ekonomi dan Bisnis', 'parent' => 'UIKA'],
            ['kode_unit' => 'FAI', 'nama_unit' => 'Fakultas Agama Islam', 'parent' => 'UIKA'],
            ['kode_unit' => 'FT', 'nama_unit' => 'Fakultas Teknik dan Sains', 'parent' => 'UIKA'],
            ['kode_unit' => 'FPASCA', 'nama_unit' => 'Sekolah Pascasarjana', 'parent' => 'UIKA'],
            ['kode_unit' => 'FIKES', 'nama_unit' => 'Fakultas Ilmu Kesehatan', 'parent' => 'UIKA'],

            // Program Studi di bawah Fakultas Keguruan dan Ilmu Pendidikan (FKIP)
            ['kode_unit' => '82119', 'nama_unit' => 'Pendidikan Vokasional Desain Fashion', 'parent' => 'FKIP'],
            ['kode_unit' => '84202', 'nama_unit' => 'Pendidikan Matematika', 'parent' => 'FKIP'],
            ['kode_unit' => '86203', 'nama_unit' => 'Teknologi Pendidikan', 'parent' => 'FKIP'],
            ['kode_unit' => '86227', 'nama_unit' => 'Pendidikan Masyarakat', 'parent' => 'FKIP'],
            ['kode_unit' => '86906', 'nama_unit' => 'Pendidikan Profesi Guru', 'parent' => 'FKIP'],
            ['kode_unit' => '88203', 'nama_unit' => 'Pendidikan Bahasa Inggris', 'parent' => 'FKIP'],

            // Program Studi di bawah Fakultas Hukum (02)
            ['kode_unit' => '74201', 'nama_unit' => 'Ilmu Hukum', 'parent' => '02'],

            // Program Studi di bawah Fakultas Ekonomi dan Bisnis (03)
            ['kode_unit' => '61318', 'nama_unit' => 'Perbankan dan Keuangan Digital', 'parent' => '03'],
            ['kode_unit' => '94205', 'nama_unit' => 'Perdagangan Internasional', 'parent' => '03'],
            ['kode_unit' => '61209', 'nama_unit' => 'Bisnis Digital', 'parent' => '03'],
            ['kode_unit' => '62201', 'nama_unit' => 'Akuntansi', 'parent' => '03'],
            ['kode_unit' => '61201', 'nama_unit' => 'Manajemen', 'parent' => '03'],

            // Program Studi di bawah Fakultas Agama Islam (04)
            ['kode_unit' => '70232', 'nama_unit' => 'Bimbingan dan Konseling Pendidikan Islam', 'parent' => '04'],
            ['kode_unit' => '86208', 'nama_unit' => 'Pendidikan Agama Islam', 'parent' => '04'],
            ['kode_unit' => '74230', 'nama_unit' => 'Ahwal Al Syakshiyah', 'parent' => '04'],
            ['kode_unit' => '70233', 'nama_unit' => 'Komunikasi dan Penyiaran Islam', 'parent' => '04'],
            ['kode_unit' => '60202', 'nama_unit' => 'Ekonomi Syariah', 'parent' => '04'],
            ['kode_unit' => '86232', 'nama_unit' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'parent' => '04'],
            ['kode_unit' => '61205', 'nama_unit' => 'Manajemen Haji dan Umrah', 'parent' => '04'],
            ['kode_unit' => '76231', 'nama_unit' => 'Ilmu Al-Qu`an dan Tafsir', 'parent' => '04'],

            // Program Studi di bawah Fakultas Teknik dan Sains (05)
            ['kode_unit' => '25201', 'nama_unit' => 'Ilmu Lingkungan', 'parent' => '05'],
            ['kode_unit' => '57201', 'nama_unit' => 'Sistem Informasi', 'parent' => '05'],
            ['kode_unit' => '54208', 'nama_unit' => 'Rekayasa Pertanian dan Biosistem', 'parent' => '05'],
            ['kode_unit' => '20201', 'nama_unit' => 'Teknik Elektro', 'parent' => '05'],
            ['kode_unit' => '22201', 'nama_unit' => 'Teknik Sipil', 'parent' => '05'],
            ['kode_unit' => '21201', 'nama_unit' => 'Teknik Mesin', 'parent' => '05'],
            ['kode_unit' => '55201', 'nama_unit' => 'Teknik Informatika', 'parent' => '05'],
            
            // Program Studi di bawah Sekolah Pascasarjana (06)
            ['kode_unit' => '61101', 'nama_unit' => 'Manajemen (S2)', 'parent' => '06'],
            ['kode_unit' => '86131', 'nama_unit' => 'Pendidikan Agama Islam (S2)', 'parent' => '06'],
            ['kode_unit' => '74107', 'nama_unit' => 'Hukum Bisnis (S2)', 'parent' => '06'],
            ['kode_unit' => '60002', 'nama_unit' => 'Ekonomi Syariah (S3)', 'parent' => '06'],
            ['kode_unit' => '86030', 'nama_unit' => 'Pendidikan Agama Islam (S3)', 'parent' => '06'],
            ['kode_unit' => '86103', 'nama_unit' => 'Teknologi Pendidikan (S2)', 'parent' => '06'],
            ['kode_unit' => '60102', 'nama_unit' => 'Ekonomi Syariah (S2)', 'parent' => '06'],
            ['kode_unit' => '70133', 'nama_unit' => 'Komunikasi Penyiaran islam (S2)', 'parent' => '06'],
            
            // Program Studi di bawah Fakultas Ilmu Kesehatan (07)
            ['kode_unit' => '13211', 'nama_unit' => 'Gizi', 'parent' => '07'],
            ['kode_unit' => '13201', 'nama_unit' => 'Kesehatan Masyarakat', 'parent' => '07'],

            // Unit Lain-lain (Anak dari Universitas)
            ['kode_unit' => '61406', 'nama_unit' => 'Keuangan Dan Perbankan', 'parent' => 'UIKA'],
            ['kode_unit' => '86205', 'nama_unit' => 'Pendidikan Luar Sekolah', 'parent' => 'UIKA'],
            ['kode_unit' => 'AU0102', 'nama_unit' => 'Bank Amanah Ummah', 'parent' => 'UIKA'],
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
