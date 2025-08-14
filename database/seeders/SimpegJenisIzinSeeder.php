<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpegJenisIzinSeeder extends Seeder
{
    public function run()
    {
        // --- PERBAIKAN 1: Ambil UUID dari tabel relasi (simpeg_jenis_kehadiran) ---
        // Asumsikan di tabel jenis_kehadiran ada nama 'Sakit' dan 'Izin'.
        $kehadiranSakitId = DB::table('simpeg_jenis_kehadiran')->where('nama_jenis', 'Sakit')->value('id');
        $kehadiranIzinId = DB::table('simpeg_jenis_kehadiran')->where('nama_jenis', 'Izin')->value('id');

        // Validasi: Hentikan seeder jika data referensi tidak ditemukan.
        if (!$kehadiranSakitId || !$kehadiranIzinId) {
            $this->command->error('Data untuk "Sakit" atau "Izin" tidak ditemukan di tabel simpeg_jenis_kehadiran.');
            $this->command->info('Harap jalankan SimpegJenisKehadiranSeeder terlebih dahulu.');
            return;
        }

        $now = Carbon::now();

        // --- PERBAIKAN 2: Ganti ID integer dengan variabel UUID ---
        $jenisIzinData = [
            [
                'id' => Str::uuid(),
                'kode' => '001',
                'jenis_izin' => 'Sakit',
                'jenis_kehadiran_id' => $kehadiranSakitId,
                'izin_max' => 3,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '002',
                'jenis_izin' => 'Izin Menikah',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 3,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '003',
                'jenis_izin' => 'Izin Menikahkan Anak',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 2,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '004',
                'jenis_izin' => 'Izin Mengkhitankan Anak',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 2,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '005',
                'jenis_izin' => 'Izin Melahirkan',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 90,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '006',
                'jenis_izin' => 'Izin Istri Melahirkan',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 2,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '007',
                'jenis_izin' => 'Izin Anak Sakit',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 2,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '008',
                'jenis_izin' => 'Izin Kondisi berduka/keluarga wafat',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 2,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '009',
                'jenis_izin' => 'Izin Ibadah Haji',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 40,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '010',
                'jenis_izin' => 'Izin Ibadah Umrah',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 14,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => Str::uuid(),
                'kode' => '999',
                'jenis_izin' => 'Izin Lain-lain',
                'jenis_kehadiran_id' => $kehadiranIzinId,
                'izin_max' => 3,
                'potong_cuti' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ];

        // --- PERBAIKAN 3: Gunakan upsert untuk efisiensi ---
        // Pastikan kolom 'kode' memiliki unique constraint di migration.
        DB::table('simpeg_jenis_izin')->upsert(
            $jenisIzinData,
            ['kode'], // Kolom unik untuk dicocokkan
            ['jenis_izin', 'jenis_kehadiran_id', 'izin_max', 'potong_cuti', 'updated_at'] // Kolom yang di-update
        );
    }
}
