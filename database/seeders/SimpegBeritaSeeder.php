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

        // --- PERBAIKAN 1: Ambil UUID dari tabel referensi ---
        // Asumsikan tabel-tabel ini juga menggunakan UUID.
        $unitKerjaId = DB::table('simpeg_unit_kerja')->where('nama_unit', 'Universitas Ibn Khaldun')->value('id');
        $jabatanIds = DB::table('simpeg_jabatan_akademik')->pluck('id')->toArray();

        // Validasi data referensi
        if (!$unitKerjaId || empty($jabatanIds)) {
            $this->command->error('Tabel referensi (unit kerja/jabatan akademik) kosong. Jalankan seeder yang relevan terlebih dahulu.');
            return;
        }

        // --- Berita Pertama ---
        if (!DB::table('simpeg_berita')->where('judul', 'Harap diperhatikan')->exists()) {
            
            // --- PERBAIKAN 2: Buat UUID SEBELUM insert ---
            $berita1Uuid = Str::uuid();
            
            DB::table('simpeg_berita')->insert([
                'id' => $berita1Uuid, // Gunakan UUID yang sudah dibuat
                'unit_kerja_id' => json_encode([$unitKerjaId]),
                'judul' => 'Harap diperhatikan',
                'konten' => 'Harap semua dosen dan tendik melengkapi Biodata yang masih belum terisi',
                'slug' => Str::slug('Harap diperhatikan') . '-' . time(),
                'tgl_posting' => '2025-01-01',
                'tgl_expired' => '2025-12-31',
                'prioritas' => false,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            // Insert relasi berita 1 dengan jabatan akademik
            $berita1Relations = array_map(function ($jabatanId) use ($berita1Uuid, $now) {
                return [
                    'id' => Str::uuid(), // Tambahkan UUID untuk tabel pivot itu sendiri
                    'berita_id' => $berita1Uuid, // Gunakan UUID berita yang benar
                    'jabatan_akademik_id' => $jabatanId,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }, $jabatanIds);
            
            DB::table('simpeg_berita_jabatan_akademik')->insert($berita1Relations);
            $this->command->info('Berita "Harap diperhatikan" berhasil ditambahkan.');
        } else {
            $this->command->info('Berita "Harap diperhatikan" sudah ada, melewati penambahan.');
        }
        
        // --- Berita Kedua ---
        if (!DB::table('simpeg_berita')->where('judul', 'Pengajian Rutin Jum\'at')->exists()) {
            
            // Buat UUID SEBELUM insert
            $berita2Uuid = Str::uuid();
            
            DB::table('simpeg_berita')->insert([
                'id' => $berita2Uuid, // Gunakan UUID yang sudah dibuat
                'unit_kerja_id' => json_encode([$unitKerjaId]),
                'judul' => 'Pengajian Rutin Jum\'at',
                'konten' => 'Pengajian rutin Jum\'at dan Do\'a Khatam Al Qur\'an Keluarga Civitas Akademika UIKA Bogor. selama Bulan Ramadhan Pukul : 17.00 wib s /d selesai',
                'slug' => Str::slug('Pengajian Rutin Jumat') . '-' . time(),
                'tgl_posting' => '2025-01-01',
                'tgl_expired' => '2025-12-31',
                'prioritas' => false,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            // Insert relasi berita 2 dengan jabatan akademik
            $berita2Relations = array_map(function ($jabatanId) use ($berita2Uuid, $now) {
                return [
                    'id' => Str::uuid(), // Tambahkan UUID untuk tabel pivot itu sendiri
                    'berita_id' => $berita2Uuid, // Gunakan UUID berita yang benar
                    'jabatan_akademik_id' => $jabatanId,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }, $jabatanIds);
            
            DB::table('simpeg_berita_jabatan_akademik')->insert($berita2Relations);
            $this->command->info('Berita "Pengajian Rutin Jum\'at" berhasil ditambahkan.');
        } else {
            $this->command->info('Berita "Pengajian Rutin Jum\'at" sudah ada, melewati penambahan.');
        }
        
        $this->command->info('Seeder berita selesai dijalankan.');
    }
}
