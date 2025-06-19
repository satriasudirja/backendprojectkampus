<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegPegawai;
use App\Models\SimpegEvaluasiKinerja;
use App\Models\SimpegDataJabatanStruktural;
use Carbon\Carbon;
use Faker\Factory as Faker;

class SimpegEvaluasiKinerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Ambil semua pegawai yang punya jabatan struktural aktif (yang akan dinilai)
        $pegawaiUntukDinilai = SimpegPegawai::whereHas('dataJabatanStruktural', function ($query) {
            $query->whereNull('tgl_selesai');
        })->get();

        $this->command->info("Mencoba membuat data evaluasi untuk {$pegawaiUntukDinilai->count()} pegawai.");
        $createdCount = 0;

        foreach ($pegawaiUntukDinilai as $pegawai) {
            try {
                // 1. Dapatkan informasi penilai dan atasan penilai
                $atasanInfo = $this->getPenilaiDanAtasan($pegawai);

                // Jika tidak ada penilai atau atasan penilai, lewati pegawai ini
                if (!$atasanInfo['penilai'] || !$atasanInfo['atasan_penilai']) {
                    $this->command->warn("Tidak dapat menemukan struktur atasan untuk: {$pegawai->nama}. Melewati.");
                    continue;
                }
                
                // Jangan menilai diri sendiri
                if($pegawai->id === $atasanInfo['penilai']->id || $pegawai->id === $atasanInfo['atasan_penilai']->id) {
                     $this->command->warn("Pegawai {$pegawai->nama} adalah atasannya sendiri. Melewati.");
                     continue;
                }

                $nilaiPendidikan = $faker->randomFloat(2, 70, 95);
                $nilaiPenelitian = $faker->randomFloat(2, 65, 98);
                $nilaiPengabdian = $faker->randomFloat(2, 75, 97);
                $totalNilai = ($nilaiPendidikan + $nilaiPenelitian + $nilaiPengabdian) / 3;

                SimpegEvaluasiKinerja::create([
                    'pegawai_id' => $pegawai->id,
                    'penilai_id' => $atasanInfo['penilai']->id,
                    'atasan_penilai_id' => $atasanInfo['atasan_penilai']->id,
                    'jenis_kinerja' => 'dosen',
                    'periode_tahun' => '2024',
                    'tanggal_penilaian' => $faker->dateTimeThisYear(),
                    'nilai_pendidikan' => $nilaiPendidikan,
                    'nilai_penelitian' => $nilaiPenelitian,
                    'nilai_pengabdian' => $nilaiPengabdian,
                    'total_nilai' => $totalNilai,
                    'sebutan_total' => $this->calculateSebutan($totalNilai),
                    'tgl_input' => Carbon::now(),
                ]);

                $createdCount++;
                if($createdCount >= 20) break; // Batasi 20 data agar tidak terlalu banyak

            } catch (\Exception $e) {
                $this->command->error("Gagal membuat evaluasi untuk {$pegawai->nama}: " . $e->getMessage());
            }
        }
        
        $this->command->info("Berhasil membuat {$createdCount} data evaluasi kinerja.");
    }

    private function getPenilaiDanAtasan(SimpegPegawai $pegawai)
    {
        $jabatanPegawai = $pegawai->dataJabatanStruktural()->whereNull('tgl_selesai')->with('jabatanStruktural.parent')->first();
        if (!$jabatanPegawai || !$jabatanPegawai->jabatanStruktural || !$jabatanPegawai->jabatanStruktural->parent) {
            return ['penilai' => null, 'atasan_penilai' => null];
        }

        $jabatanPenilaiStruktural = $jabatanPegawai->jabatanStruktural->parent;
        $dataJabatanPenilai = SimpegDataJabatanStruktural::where('jabatan_struktural_id', $jabatanPenilaiStruktural->id)
            ->whereNull('tgl_selesai')->first();
        if (!$dataJabatanPenilai) return ['penilai' => null, 'atasan_penilai' => null];
        
        $penilai = SimpegPegawai::find($dataJabatanPenilai->pegawai_id);

        if (!$jabatanPenilaiStruktural->parent) return ['penilai' => $penilai, 'atasan_penilai' => null];
        
        $jabatanAtasanPenilaiStruktural = $jabatanPenilaiStruktural->parent;
        $dataJabatanAtasanPenilai = SimpegDataJabatanStruktural::where('jabatan_struktural_id', $jabatanAtasanPenilaiStruktural->id)
            ->whereNull('tgl_selesai')->first();
        if (!$dataJabatanAtasanPenilai) return ['penilai' => $penilai, 'atasan_penilai' => null];
        
        $atasanPenilai = SimpegPegawai::find($dataJabatanAtasanPenilai->pegawai_id);

        return ['penilai' => $penilai, 'atasan_penilai' => $atasanPenilai];
    }

    private function calculateSebutan($nilai)
    {
        if ($nilai >= 91) return 'Sangat Baik';
        if ($nilai >= 76) return 'Baik';
        if ($nilai >= 61) return 'Cukup';
        if ($nilai >= 51) return 'Kurang';
        return 'Sangat Kurang';
    }
}
