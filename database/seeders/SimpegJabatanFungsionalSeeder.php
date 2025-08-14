<?php

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
        // --- PERBAIKAN 1: Ambil UUID dari tabel relasi ---
        // Buat peta (map) dari nama/kode ke UUID untuk kemudahan pencarian.
        $jabatanAkademikMap = DB::table('simpeg_jabatan_akademik')->pluck('id', 'jabatan_akademik');
        $pangkatMap = DB::table('simpeg_master_pangkat')->pluck('id', 'pangkat');

        // Validasi: Pastikan data referensi ada.
        if ($jabatanAkademikMap->isEmpty() || $pangkatMap->isEmpty()) {
            $this->command->error('Tabel simpeg_jabatan_akademik atau simpeg_master_pangkat kosong.');
            $this->command->info('Harap jalankan seeder yang relevan terlebih dahulu.');
            return;
        }

        $now = Carbon::now();

        // --- PERBAIKAN 2: Ganti ID integer dengan UUID dari peta (map) ---
        $jabatanFungsional = [
            // Asisten Ahli
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Asisten Ahli'] ?? null,
                'pangkat_id' => $pangkatMap['III/a'] ?? null,
                'kode' => 'AA',
                'kode_jabatan_akademik' => 'AA',
                'nama_jabatan_fungsional' => 'Asisten Ahli',
                'pangkat' => 'III/a',
                'angka_kredit' => '100',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen pemula',
            ],
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Asisten Ahli'] ?? null,
                'pangkat_id' => $pangkatMap['III/b'] ?? null,
                'kode' => 'AA1',
                'kode_jabatan_akademik' => 'AA',
                'nama_jabatan_fungsional' => 'Asisten Ahli',
                'pangkat' => 'III/b',
                'angka_kredit' => '150',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen pemula tingkat lanjut',
            ],
            // Lektor
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Lektor'] ?? null,
                'pangkat_id' => $pangkatMap['III/c'] ?? null,
                'kode' => 'L',
                'kode_jabatan_akademik' => 'L',
                'nama_jabatan_fungsional' => 'Lektor',
                'pangkat' => 'III/c',
                'angka_kredit' => '200',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen madya',
            ],
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Lektor'] ?? null,
                'pangkat_id' => $pangkatMap['III/d'] ?? null,
                'kode' => 'L1',
                'kode_jabatan_akademik' => 'L1',
                'nama_jabatan_fungsional' => 'Lektor',
                'pangkat' => 'III/d',
                'angka_kredit' => '300',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen madya tingkat lanjut',
            ],
            // Lektor Kepala
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Lektor Kepala'] ?? null,
                'pangkat_id' => $pangkatMap['IV/a'] ?? null,
                'kode' => 'LK',
                'kode_jabatan_akademik' => 'LK',
                'nama_jabatan_fungsional' => 'Lektor Kepala',
                'pangkat' => 'IV/a',
                'angka_kredit' => '400',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen senior',
            ],
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Lektor Kepala'] ?? null,
                'pangkat_id' => $pangkatMap['IV/b'] ?? null,
                'kode' => 'LK1',
                'kode_jabatan_akademik' => 'LK',
                'nama_jabatan_fungsional' => 'Lektor Kepala',
                'pangkat' => 'IV/b',
                'angka_kredit' => '550',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen senior tingkat lanjut',
            ],
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Lektor Kepala'] ?? null,
                'pangkat_id' => $pangkatMap['IV/c'] ?? null,
                'kode' => 'LK2',
                'kode_jabatan_akademik' => 'LK',
                'nama_jabatan_fungsional' => 'Lektor Kepala',
                'pangkat' => 'IV/c',
                'angka_kredit' => '700',
                'usia_pensiun' => 65,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen senior tingkat utama',
            ],
            // Guru Besar
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Guru Besar'] ?? null,
                'pangkat_id' => $pangkatMap['IV/d'] ?? null,
                'kode' => 'GB',
                'kode_jabatan_akademik' => 'GB',
                'nama_jabatan_fungsional' => 'Guru Besar',
                'pangkat' => 'IV/d',
                'angka_kredit' => '850',
                'usia_pensiun' => 70,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen tertinggi',
            ],
            [
                'id' => Str::uuid(),
                'jabatan_akademik_id' => $jabatanAkademikMap['Guru Besar'] ?? null,
                'pangkat_id' => $pangkatMap['IV/e'] ?? null,
                'kode' => 'GB1',
                'kode_jabatan_akademik' => 'GB',
                'nama_jabatan_fungsional' => 'Guru Besar',
                'pangkat' => 'IV/e',
                'angka_kredit' => '1050',
                'usia_pensiun' => 70,
                'tunjangan' => 30000000,
                'keterangan' => 'Jabatan fungsional dosen tertinggi',
            ],
        ];

        // Tambahkan timestamps ke setiap record
        $dataToInsert = array_map(function ($item) use ($now) {
            // Hanya proses jika foreign key ditemukan
            if ($item['jabatan_akademik_id'] && $item['pangkat_id']) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
                return $item;
            }
            return null;
        }, $jabatanFungsional);

        // Hapus nilai null yang mungkin terjadi jika ada foreign key yang tidak ditemukan
        $dataToInsert = array_filter($dataToInsert);

        // Kosongkan tabel dan insert data baru
        DB::table('simpeg_jabatan_fungsional')->truncate();
        DB::table('simpeg_jabatan_fungsional')->insert($dataToInsert);
    }
}
