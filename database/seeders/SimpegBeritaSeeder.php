<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SimpegBeritaSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        // Gunakan unit_kerja_id yang tetap, tanpa perlu query ke tabel unit_kerja
        $unitKerjaId = 1; // Anggap ID 1 adalah Universitas Ibn Khaldun
        
        // Pastikan tabel simpeg_jabatan_akademik sudah ada dan berisi data
        $jabatanExist = DB::table('simpeg_jabatan_akademik')->exists();
        
        if (!$jabatanExist) {
            $this->command->info('Tabel simpeg_jabatan_akademik kosong. Silakan jalankan seeder jabatan akademik terlebih dahulu.');
            return;
        }
        
        // Dapatkan ID dari jabatan akademik
        $jabatanIds = DB::table('simpeg_jabatan_akademik')
                        ->whereIn('jabatan_akademik', [
                            'Administrasi', 'Pustakawan', 'Dosen', 
                            'Rumah Tangga', 'Tenaga Pengajar'
                        ])
                        ->pluck('id')
                        ->toArray();
                        
        $jabatanIds2 = DB::table('simpeg_jabatan_akademik')
                         ->whereIn('jabatan_akademik', [
                            'Asisten Ahli', 'Guru Besar', 'Administrasi', 
                            'Laboran', 'Pustakawan', 'Lektor', 
                            'Lektor Kepala', 'Rumah Tangga', 'Tenaga Pengajar'
                         ])
                         ->pluck('id')
                         ->toArray();
        
        // Cek apakah ada jabatan yang ditemukan
        if (empty($jabatanIds) || empty($jabatanIds2)) {
            $this->command->info('Tidak semua jabatan akademik ditemukan. Silakan periksa data jabatan akademik.');
            return;
        }

        // Periksa apakah berita sudah ada
        $existingBerita1 = DB::table('simpeg_berita')
            ->where('judul', 'Harap diperhatikan')
            ->first();

        // Insert berita pertama jika belum ada
        if (!$existingBerita1) {
            // Tambahkan timestamp ke slug untuk memastikan keunikan
            $uniqueSlug1 = Str::slug('Harap diperhatikan') . '-' . time();
            
            $berita1 = [
                'unit_kerja_id' => json_encode([$unitKerjaId]),
                'judul' => 'Harap diperhatikan',
                'konten' => 'Harap semua dosen dan tendik melengkapi Biodata yang masih belum terisi',
                'slug' => $uniqueSlug1,
                'tgl_posting' => '2025-01-01',
                'tgl_expired' => '2025-12-31',
                'prioritas' => false,
                'gambar_berita' => null,
                'file_berita' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
            
            $berita1Id = DB::table('simpeg_berita')->insertGetId($berita1);
            
            // Insert relasi berita 1 dengan jabatan akademik
            $berita1Relations = array_map(function ($jabatanId) use ($berita1Id, $now) {
                return [
                    'berita_id' => $berita1Id,
                    'jabatan_akademik_id' => $jabatanId,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }, $jabatanIds);
            
            DB::table('simpeg_berita_jabatan_akademik')->insert($berita1Relations);
        } else {
            $berita1Id = $existingBerita1->id;
            $this->command->info('Berita "Harap diperhatikan" sudah ada, melewati penambahan.');
        }
        
        // Periksa apakah berita kedua sudah ada
        $existingBerita2 = DB::table('simpeg_berita')
            ->where('judul', 'Pengajian Rutin Jum\'at')
            ->first();

        // Insert berita kedua jika belum ada
        if (!$existingBerita2) {
            // Tambahkan timestamp ke slug untuk memastikan keunikan
            $uniqueSlug2 = Str::slug('Pengajian Rutin Jumat') . '-' . time();
            
            $berita2 = [
                'unit_kerja_id' => json_encode([$unitKerjaId]),
                'judul' => 'Pengajian Rutin Jum\'at',
                'konten' => 'Pengajian rutin Jum\'at dan Do\'a Khatam Al Qur\'an Keluarga Civitas Akademika UIKA Bogor. selama Bulan Ramadhan Pukul : 17.00 wib  s /d selesai',
                'slug' => $uniqueSlug2,
                'tgl_posting' => '2025-01-01',
                'tgl_expired' => '2025-12-31',
                'prioritas' => false,
                'gambar_berita' => null,
                'file_berita' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
            
            $berita2Id = DB::table('simpeg_berita')->insertGetId($berita2);
            
            // Insert relasi berita 2 dengan jabatan akademik
            $berita2Relations = array_map(function ($jabatanId) use ($berita2Id, $now) {
                return [
                    'berita_id' => $berita2Id,
                    'jabatan_akademik_id' => $jabatanId,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }, $jabatanIds2);
            
            DB::table('simpeg_berita_jabatan_akademik')->insert($berita2Relations);
        } else {
            $berita2Id = $existingBerita2->id;
            $this->command->info('Berita "Pengajian Rutin Jum\'at" sudah ada, melewati penambahan.');
        }
        
        $this->command->info('Seeder berita berhasil dijalankan.');
    }
}