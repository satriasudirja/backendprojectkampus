<?php

// 1. FIXED SimpegJabatanFungsionalSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpegJabatanFungsionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // REMOVED: Tidak lagi ambil data jabatan akademik karena tidak ada relasi
        // $jabatanAkademikMap = DB::table('simpeg_jabatan_akademik')->pluck('id', 'jabatan_akademik');
        
        // Ambil data pangkat jika masih diperlukan (opsional)
        $pangkatMap = DB::table('simpeg_master_pangkat')->pluck('id', 'pangkat');

        // Validasi: Pangkat bisa optional, jadi tidak perlu validasi strict
        if ($pangkatMap->isEmpty()) {
            $this->command->warn('Tabel simpeg_master_pangkat kosong. Jabatan fungsional akan dibuat tanpa relasi pangkat.');
        }

        $now = Carbon::now();

        // UPDATED: Data jabatan fungsional tanpa relasi ke jabatan akademik
        $jabatanFungsional = [
            // Asisten Ahli
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['III/a'] ?? null,
                'kode' => 'AA3A',
                'nama_jabatan_fungsional' => 'Asisten Ahli III/a',
                'pangkat' => 'III/a',
                'angka_kredit' => '100',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen pemula dengan pangkat III/a',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['III/b'] ?? null,
                'kode' => 'AA3B',
                'nama_jabatan_fungsional' => 'Asisten Ahli III/b',
                'pangkat' => 'III/b',
                'angka_kredit' => '150',
                'usia_pensiun' => 65,
                'tunjangan' => 32000000,
                'keterangan' => 'Jabatan fungsional dosen pemula dengan pangkat III/b',
            ],

            // Lektor
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['III/c'] ?? null,
                'kode' => 'L3C',
                'nama_jabatan_fungsional' => 'Lektor III/c',
                'pangkat' => 'III/c',
                'angka_kredit' => '200',
                'usia_pensiun' => 65,
                'tunjangan' => 35000000,
                'keterangan' => 'Jabatan fungsional dosen madya dengan pangkat III/c',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['III/d'] ?? null,
                'kode' => 'L3D',
                'nama_jabatan_fungsional' => 'Lektor III/d',
                'pangkat' => 'III/d',
                'angka_kredit' => '300',
                'usia_pensiun' => 65,
                'tunjangan' => 38000000,
                'keterangan' => 'Jabatan fungsional dosen madya dengan pangkat III/d',
            ],

            // Lektor Kepala
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['IV/a'] ?? null,
                'kode' => 'LK4A',
                'nama_jabatan_fungsional' => 'Lektor Kepala IV/a',
                'pangkat' => 'IV/a',
                'angka_kredit' => '400',
                'usia_pensiun' => 65,
                'tunjangan' => 42000000,
                'keterangan' => 'Jabatan fungsional dosen senior dengan pangkat IV/a',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['IV/b'] ?? null,
                'kode' => 'LK4B',
                'nama_jabatan_fungsional' => 'Lektor Kepala IV/b',
                'pangkat' => 'IV/b',
                'angka_kredit' => '550',
                'usia_pensiun' => 65,
                'tunjangan' => 45000000,
                'keterangan' => 'Jabatan fungsional dosen senior dengan pangkat IV/b',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['IV/c'] ?? null,
                'kode' => 'LK4C',
                'nama_jabatan_fungsional' => 'Lektor Kepala IV/c',
                'pangkat' => 'IV/c',
                'angka_kredit' => '700',
                'usia_pensiun' => 65,
                'tunjangan' => 48000000,
                'keterangan' => 'Jabatan fungsional dosen senior dengan pangkat IV/c',
            ],

            // Guru Besar
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['IV/d'] ?? null,
                'kode' => 'GB4D',
                'nama_jabatan_fungsional' => 'Guru Besar IV/d',
                'pangkat' => 'IV/d',
                'angka_kredit' => '850',
                'usia_pensiun' => 70,
                'tunjangan' => 52000000,
                'keterangan' => 'Jabatan fungsional dosen tertinggi dengan pangkat IV/d',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['IV/e'] ?? null,
                'kode' => 'GB4E',
                'nama_jabatan_fungsional' => 'Guru Besar IV/e',
                'pangkat' => 'IV/e',
                'angka_kredit' => '1050',
                'usia_pensiun' => 70,
                'tunjangan' => 55000000,
                'keterangan' => 'Jabatan fungsional dosen tertinggi dengan pangkat IV/e',
            ],

            // Jabatan Fungsional Non-Dosen (Tenaga Kependidikan)
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['II/a'] ?? null,
                'kode' => 'ADM2A',
                'nama_jabatan_fungsional' => 'Administrasi II/a',
                'pangkat' => 'II/a',
                'angka_kredit' => '50',
                'usia_pensiun' => 58,
                'tunjangan' => 15000000,
                'keterangan' => 'Jabatan fungsional tenaga administrasi',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['II/b'] ?? null,
                'kode' => 'ADM2B',
                'nama_jabatan_fungsional' => 'Administrasi II/b',
                'pangkat' => 'II/b',
                'angka_kredit' => '75',
                'usia_pensiun' => 58,
                'tunjangan' => 17000000,
                'keterangan' => 'Jabatan fungsional tenaga administrasi tingkat lanjut',
            ],
            [
                'id' => Str::uuid(),
                'pangkat_id' => $pangkatMap['III/a'] ?? null,
                'kode' => 'TK3A',
                'nama_jabatan_fungsional' => 'Teknisi III/a',
                'pangkat' => 'III/a',
                'angka_kredit' => '100',
                'usia_pensiun' => 58,
                'tunjangan' => 22000000,
                'keterangan' => 'Jabatan fungsional teknisi',
            ],
        ];

        // Tambahkan timestamps ke setiap record
        $dataToInsert = array_map(function ($item) use ($now) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
            return $item;
        }, $jabatanFungsional);

        // Kosongkan tabel dan insert data baru
        DB::table('simpeg_jabatan_fungsional')->truncate();
        DB::table('simpeg_jabatan_fungsional')->insert($dataToInsert);

        $this->command->info('✓ Seeded ' . count($dataToInsert) . ' jabatan fungsional records');
    }
}