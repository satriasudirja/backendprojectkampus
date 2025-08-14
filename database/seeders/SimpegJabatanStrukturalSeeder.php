<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\SimpegMasterPangkat;
use App\Models\SimpegUnitKerja;

class SimpegJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        try {
            // PERBAIKAN 1: Ambil semua jenis jabatan dan buat peta dari 'kode' ke 'id' (UUID)
            $jenisJabatanMap = DB::table('simpeg_jenis_jabatan_struktural')->pluck('id', 'kode');
            
            if ($jenisJabatanMap->isEmpty()) {
                $this->command->error('Tabel simpeg_jenis_jabatan_struktural kosong! Jalankan SimpegJenisJabatanStrukturalSeeder terlebih dahulu.');
                return;
            }

            $this->command->info("Found {$jenisJabatanMap->count()} jenis jabatan struktural");

            // PERBAIKAN 2: Cari atau buat unit kerja dengan UUID yang benar
            $unitKerja = SimpegUnitKerja::where('nama_unit', 'Universitas Ibn Khaldun')->first();
            
            if (!$unitKerja) {
                // Buat unit kerja baru dengan UUID
                $unitKerja = SimpegUnitKerja::create([
                    'id' => Str::uuid(),
                    'kode_unit' => '041001',
                    'nama_unit' => 'Universitas Ibn Khaldun',
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                $this->command->info("Created new unit kerja: {$unitKerja->nama_unit} with ID: {$unitKerja->id}");
            }

            $unitKerjaId = $unitKerja->id;

            // PERBAIKAN 3: Ambil pangkat dengan error handling
            $pangkatPembina = SimpegMasterPangkat::where('pangkat', 'IV/a')->first();
            $pangkatPenata = SimpegMasterPangkat::where('pangkat', 'III/d')->first();
            $pangkatPengatur = SimpegMasterPangkat::where('pangkat', 'III/c')->first();
            $pangkatStaf = SimpegMasterPangkat::where('pangkat', 'III/a')->first();

            // Fallback jika pangkat spesifik tidak ada - ambil yang ada
            $fallbackPangkat = SimpegMasterPangkat::first();
            
            if (!$fallbackPangkat) {
                $this->command->error('Tabel simpeg_master_pangkat kosong! Jalankan SimpegMasterPangkatSeeder terlebih dahulu.');
                return;
            }

            if (!$pangkatPembina) $pangkatPembina = $fallbackPangkat;
            if (!$pangkatPenata) $pangkatPenata = $fallbackPangkat;
            if (!$pangkatPengatur) $pangkatPengatur = $fallbackPangkat;
            if (!$pangkatStaf) $pangkatStaf = $fallbackPangkat;

            $this->command->info("Using pangkat - Pembina: {$pangkatPembina->pangkat}, Penata: {$pangkatPenata->pangkat}");

            // PERBAIKAN 4: Ambil eselon dengan error handling
            $eselonI = DB::table('simpeg_eselon')->where('nama_eselon', 'IA')->first();
            $eselonII = DB::table('simpeg_eselon')->where('nama_eselon', 'IIA')->first();
            $eselonIII = DB::table('simpeg_eselon')->where('nama_eselon', 'IIIA')->first();
            $eselonIV = DB::table('simpeg_eselon')->where('nama_eselon', 'IVA')->first();
            
            // Fallback jika eselon spesifik tidak ada
            $fallbackEselon = DB::table('simpeg_eselon')->first();
            
            if (!$fallbackEselon) {
                $this->command->error('Tabel simpeg_eselon kosong! Jalankan SimpegEselonSeeder terlebih dahulu.');
                return;
            }

            if (!$eselonI) $eselonI = $fallbackEselon;
            if (!$eselonII) $eselonII = $fallbackEselon;
            if (!$eselonIII) $eselonIII = $fallbackEselon;
            if (!$eselonIV) $eselonIV = $fallbackEselon;

            $this->command->info("Using eselon - I: {$eselonI->nama_eselon}, II: {$eselonII->nama_eselon}");

            // PERBAIKAN 5: Validasi kode jenis jabatan yang akan digunakan
            $requiredKodes = ['10000', '11000', '11001', '11100', '11101', '21000', '20000', '11300', '11400', '12000', '12112', '12120', '12130', '12110', '12111', '12160', '11200'];
            $missingKodes = [];
            
            foreach ($requiredKodes as $kode) {
                if (!$jenisJabatanMap->has($kode)) {
                    $missingKodes[] = $kode;
                }
            }

            if (!empty($missingKodes)) {
                $this->command->warn("Beberapa kode jenis jabatan tidak ditemukan: " . implode(', ', $missingKodes));
                $this->command->warn("Akan menggunakan jenis jabatan pertama sebagai fallback");
                
                $fallbackJenisJabatan = $jenisJabatanMap->first();
                foreach ($missingKodes as $kode) {
                    $jenisJabatanMap[$kode] = $fallbackJenisJabatan;
                }
            }

            // Data jabatan struktural
            $data = [
                // Level Universitas
                [
                    'id' => Str::uuid(), 
                    'kode' => '001', 
                    'singkatan' => 'Rektor', 
                    'parent_jabatan' => null, 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['10000'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPembina->id, 
                    'eselon_id' => $eselonI->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '002', 
                    'singkatan' => 'Wakil Rektor Bidang Akademik', 
                    'parent_jabatan' => '001', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11000'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '003', 
                    'singkatan' => 'Wakil Rektor Bidang Pengelolaan Sumberdaya', 
                    'parent_jabatan' => '001', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11001'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '004', 
                    'singkatan' => 'Wakil Rektor Bidang Kemahasiswaan dan Dakwah', 
                    'parent_jabatan' => '001', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11100'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '005', 
                    'singkatan' => 'Wakil Rektor Bidang Kerjasama, Inovasi dan Pengembangan', 
                    'parent_jabatan' => '001', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11101'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '006', 
                    'singkatan' => 'Sekretaris Rektor', 
                    'parent_jabatan' => '001', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['21000'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => false, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '007', 
                    'singkatan' => 'Staf Ahli Rektor Bidang Akademik dan Publikasi Ilmiah', 
                    'parent_jabatan' => '002', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['20000'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIV->id, 
                    'is_pimpinan' => false, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '008', 
                    'singkatan' => 'Staf Ahli Rektor Bidang Kemahasiswaan, Kerjasama dan Dakwah', 
                    'parent_jabatan' => '004', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['20000'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIV->id, 
                    'is_pimpinan' => false, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],

                // Level Biro dan Lembaga
                [
                    'id' => Str::uuid(), 
                    'kode' => '029', 
                    'singkatan' => 'Kepala Lembaga Penelitian dan Pengabdian Kepada Masyarakat', 
                    'parent_jabatan' => null, 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11300'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '034', 
                    'singkatan' => 'Kepala UPT Perpustakaan', 
                    'parent_jabatan' => null, 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11400'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '040', 
                    'singkatan' => 'Ketua Kantor Penjaminan Mutu dan Audit Internal', 
                    'parent_jabatan' => null, 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11400'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],

                // Level Fakultas
                [
                    'id' => Str::uuid(), 
                    'kode' => '052', 
                    'singkatan' => 'Dekan', 
                    'parent_jabatan' => null, 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12000'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '053', 
                    'singkatan' => 'Wakil Dekan Bidang Akademik', 
                    'parent_jabatan' => '052', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12112'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '054', 
                    'singkatan' => 'Wakil Dekan Bidang Pengelolaan Sumberdaya', 
                    'parent_jabatan' => '052', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12120'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '055', 
                    'singkatan' => 'Wakil Dekan Bidang Kemahasiswaan, Kerjasama dan Dakwah', 
                    'parent_jabatan' => '052', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12130'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '056', 
                    'singkatan' => 'Ketua Program Studi', 
                    'parent_jabatan' => '052', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12110'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '057', 
                    'singkatan' => 'Sekretaris Program Studi', 
                    'parent_jabatan' => '052', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12111'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatStaf->id, 
                    'eselon_id' => $eselonIV->id, 
                    'is_pimpinan' => false, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '058', 
                    'singkatan' => 'Kepala Laboratorium', 
                    'parent_jabatan' => '052', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12160'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatStaf->id, 
                    'eselon_id' => $eselonIV->id, 
                    'is_pimpinan' => false, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                
                // Level Pascasarjana
                [
                    'id' => Str::uuid(), 
                    'kode' => '070', 
                    'singkatan' => 'Direktur Sekolah Pascasarjana', 
                    'parent_jabatan' => null, 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['11200'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPenata->id, 
                    'eselon_id' => $eselonII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '071', 
                    'singkatan' => 'Wakil Direktur Bidang Akademik, Inovasi, dan Kemahasiswaan', 
                    'parent_jabatan' => '070', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12112'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
                [
                    'id' => Str::uuid(), 
                    'kode' => '072', 
                    'singkatan' => 'Wakil Direktur Bidang Sumberdaya, Kerjasama dan Dakwah', 
                    'parent_jabatan' => '070', 
                    'jenis_jabatan_struktural_id' => $jenisJabatanMap['12120'], 
                    'unit_kerja_id' => $unitKerjaId, 
                    'pangkat_id' => $pangkatPengatur->id, 
                    'eselon_id' => $eselonIII->id, 
                    'is_pimpinan' => true, 
                    'aktif' => true, 
                    'tunjangan' => 24000000, 
                    'created_at' => $now, 
                    'updated_at' => $now
                ],
            ];

            // Clear existing data and insert new data
            DB::table('simpeg_jabatan_struktural')->delete();
            
            // FIX: Use proper string concatenation instead of curly braces with function call
            $dataCount = count($data);
            $this->command->info("Inserting {$dataCount} jabatan struktural records...");
            
            DB::table('simpeg_jabatan_struktural')->insert($data);
            
            $finalCount = DB::table('simpeg_jabatan_struktural')->count();
            $this->command->info("✓ Successfully created {$finalCount} jabatan struktural records");

        } catch (\Exception $e) {
            $this->command->error("❌ Error in SimpegJabatanStrukturalSeeder: " . $e->getMessage());
            $this->command->error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}