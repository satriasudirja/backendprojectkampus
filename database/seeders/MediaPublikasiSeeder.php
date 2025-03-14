<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaPublikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel media_publikasi
        $data = [
            ['nama' => 'Jurnal Profit : Kajian Pendidikan Ekonomi dan Ilmu Ekonomi'],
            ['nama' => 'Jurnal Psikologi Pendidikan dan Konseling: Jurnal Kajian Psikologi Pendidikan dan Bimbingan Konseling'],
            ['nama' => 'Jurnal Pusaka : Media Kajian dan Pemikiran Islam'],
            ['nama' => 'Jurnal Review Pendidikan Dasar : Jurnal Kajian Pendidikan dan Hasil Penelitian'],
            ['nama' => 'Jurnal Riset dan Kajian Pendidikan Fisika'],
            ['nama' => 'Jurnal Silogisme: Kajian Ilmu Matematika dan Pembelajarannya'],
            ['nama' => 'Jurnal Tarbiyatuna : Kajian Pendidikan Islam'],
            ['nama' => 'Jurnal Wahana Kajian Pendidikan IPS'],
            ['nama' => 'KENOSIS: Jurnal Kajian Teologi'],
            ['nama' => 'KEUDA (Jurnal Kajian Ekonomi dan Keuangan Daerah)'],
            ['nama' => 'KLAUSA (Kajian Linguistik, Pembelajaran Bahasa, dan Sastra)'],
            ['nama' => 'Kajian Akuntansi'],
            ['nama' => 'Kajian Bisnis STIE Widya Wiwaha'],
            ['nama' => 'Kajian Ekonomi dan Keuangan'],
            ['nama' => 'Kajian Jurnalisme'],
            ['nama' => 'Kajian Linguistik'],
            ['nama' => 'Kajian Linguistik dan Sastra'],
            ['nama' => 'Keteg : Jurnal Pengetahuan, Pemikiran dan Kajian Tentang Bunyi'],
            ['nama' => 'Konseling Komprehensif: Kajian Teori dan Praktik Bimbingan dan Konseling'],
            ['nama' => 'Lensa : Kajian Kebahasaan, Kesusastraan, dan Budaya'],
            ['nama' => 'Lentera Pustaka: Jurnal Kajian Ilmu Perpustakaan, Informasi dan Kearsipan'],
            ['nama' => 'Lex Journal : Kajian Hukum dan Keadilan'],
            ['nama' => 'Local Wisdom : Jurnal Ilmiah Kajian Kearifan Lokal'],
            ['nama' => 'Lokabasa : Jurnal Kajian Bahasa, Sastra, dan Budaya Daerah serta Pengajarannya'],
            ['nama' => 'MUDARRISA: Jurnal Kajian Pendidikan Islam'],
            ['nama' => 'Madania (Kajian Keislaman)'],
            ['nama' => 'Madania : Jurnal Kajian Keislaman'],
            ['nama' => 'Mahkamah: Jurnal Kajian Hukum Islam'],
            ['nama' => 'Majalah Ilmiah Pengkajian Industri'],
            ['nama' => 'Masyarakat Madani: Jurnal Kajian Islam dan Pengembangan Masyarakat'],
            ['nama' => 'Media Syariah: Wahana Kajian Hukum Islam dan Pranata Sosial'],
            ['nama' => 'Media Trend: Berkala Kajian Ekonomi dan Studi Pembangunan'],
            ['nama' => 'Menara Ilmu : Jurnal Penelitian dan Kajian Ilmiah'],
            ['nama' => 'MetaKom : Jurnal Kajian Komunikasi'],
            ['nama' => 'Mimbar Pendidikan: Jurnal Indonesia untuk Kajian Pendidikan'],
            ['nama' => 'Misykat al-Anwar Jurnal Kajian Islam dan Masyarakat'],
            ['nama' => 'Naturalistic : Jurnal Kajian dan Penelitian Pendidikan dan Pembelajaran'],
            ['nama' => 'Nukhbatul Ulum : Jurnal Bidang Kajian Islam'],
            ['nama' => 'OIKOS: Jurnal Kajian Pendidikan Ekonomi dan Ilmu Ekonomi'],
            ['nama' => 'ORBITA: Jurnal Kajian, Inovasi dan Aplikasi Pendidikan Fisika'],
            ['nama' => 'PROMUSIKA : Jurnal Pengkajian, Penyajian, dan Penciptaan Musik'],
            ['nama' => 'Paedagoria : Jurnal Kajian, Penelitian dan Pengembangan Kependidikan'],
            ['nama' => 'Paradigma : Jurnal Kajian Budaya'],
            ['nama' => 'Parafrase: Jurnal Kajian Kebahasaan dan Kesastraan'],
            ['nama' => 'PrimEarly : Jurnal Kajian Pendidikan Dasar dan Anak Usia Dini (Journal of Primary and Early Childhood Education Studies)'],
            ['nama' => 'Prisma Sains : Jurnal Pengkajian Ilmu dan Pembelajaran Matematika dan IPA IKIP Mataram'],
            ['nama' => 'ProTVF: Jurnal Kajian Televisi dan Film'],
            ['nama' => 'Pustakaloka : Jurnal Kajian Informasi dan Perpustakaan'],
            ['nama' => 'SUHUF, Pengembangan Kajian Keislaman'],
            ['nama' => 'Sabda : Jurnal Kajian Kebudayaan'],
            ['nama' => 'Sainstech: Jurnal Penelitian dan Pengkajian Sains dan Teknologi'],
            ['nama' => 'Sangkep: Jurnal Kajian Sosial Keagamaan'],
            ['nama' => 'Satwika : Kajian Ilmu Budaya dan Perubahan Sosial'],
            ['nama' => 'Sekolah Dasar:Kajian Teori dan Praktik Pendidikan'],
            ['nama' => 'Sorai: Jurnal Pengkajian dan Penciptaan Musik'],
            ['nama' => 'Sosiologi: Jurnal Ilmiah Kajian Ilmu Sosial dan Budaya'],
            ['nama' => 'Swarnadwipa : Jurnal Kajian Sejarah, Sosial, Budaya, dan Pembelajarannya'],
            ['nama' => 'Tahdis: Jurnal Kajian Ilmu Al-Hadis'],
            ['nama' => 'Tonil: Jurnal Kajian Sastra, Teater, dan Sinema'],
            ['nama' => 'Translitera : Jurnal Kajian Komunikasi dan Studi Media'],
            ['nama' => 'Tumbuh Kembang : Kajian Teori dan Pembelajaran PAUD'],
            ['nama' => 'Virtuoso: Jurnal Pengkajian dan Penciptaan Musik'],
        ];

        // Insert data ke tabel media_publikasi
        DB::table('media_publikasi')->insert($data);
    }
}