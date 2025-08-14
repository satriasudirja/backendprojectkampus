<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimpegGajiKomponenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data komponen gaji yang akan dimasukkan, lengkap dengan UUID
        $komponenGaji = [
            ['id' => Str::uuid(), 'kode_komponen' => 'T001', 'nama_komponen' => 'Gaji Pokok', 'jenis' => 'tunjangan', 'rumus' => null],
            ['id' => Str::uuid(), 'kode_komponen' => 'T002', 'nama_komponen' => 'Tunjangan Jabatan', 'jenis' => 'tunjangan', 'rumus' => null],
            ['id' => Str::uuid(), 'kode_komponen' => 'T003', 'nama_komponen' => 'Tunjangan Keluarga', 'jenis' => 'tunjangan', 'rumus' => 'gaji_pokok * 0.1'],
            ['id' => Str::uuid(), 'kode_komponen' => 'T004', 'nama_komponen' => 'Tunjangan Makan', 'jenis' => 'tunjangan', 'rumus' => null],
            ['id' => Str::uuid(), 'kode_komponen' => 'T005', 'nama_komponen' => 'Tunjangan Transport', 'jenis' => 'tunjangan', 'rumus' => null],
            ['id' => Str::uuid(), 'kode_komponen' => 'P001', 'nama_komponen' => 'Potongan PPh 21', 'jenis' => 'potongan', 'rumus' => 'gaji_bruto * 0.05'],
            ['id' => Str::uuid(), 'kode_komponen' => 'P002', 'nama_komponen' => 'Potongan BPJS Kesehatan', 'jenis' => 'potongan', 'rumus' => 'gaji_pokok * 0.01'],
            ['id' => Str::uuid(), 'kode_komponen' => 'P003', 'nama_komponen' => 'Potongan BPJS Ketenagakerjaan', 'jenis' => 'potongan', 'rumus' => 'gaji_pokok * 0.02'],
            ['id' => Str::uuid(), 'kode_komponen' => 'P004', 'nama_komponen' => 'Potongan Koperasi', 'jenis' => 'potongan', 'rumus' => null],
            ['id' => Str::uuid(), 'kode_komponen' => 'B001', 'nama_komponen' => 'Tunjangan Hari Raya', 'jenis' => 'benefit', 'rumus' => 'gaji_pokok * 1'],
            ['id' => Str::uuid(), 'kode_komponen' => 'B002', 'nama_komponen' => 'Bonus Tahunan', 'jenis' => 'benefit', 'rumus' => null],
            ['id' => Str::uuid(), 'kode_komponen' => 'B003', 'nama_komponen' => 'Insentif Kinerja', 'jenis' => 'benefit', 'rumus' => null],
        ];

        // --- SOLUSI ---
        // Gunakan upsert() untuk efisiensi dan penanganan UUID yang benar.
        // Ini akan melakukan satu query saja, bukan satu per satu di dalam loop.
        DB::table('simpeg_gaji_komponen')->upsert(
            $komponenGaji, // 1. Data lengkap yang akan di-insert atau di-update.
            ['kode_komponen'], // 2. Kolom unik untuk dicocokkan.
            ['nama_komponen', 'jenis', 'rumus', 'updated_at'] // 3. Kolom yang akan di-update jika data sudah ada.
        );
    }
}
