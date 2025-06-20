<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanStruktural;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegUnitKerja;
use App\Models\JenisJabatanStruktural;
use App\Models\SimpegMasterPangkat;
use App\Models\SimpegEselon; // Import model Eselon
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class SimpegDataJabatanStrukturalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create('id_ID');

        DB::statement('SET CONSTRAINTS ALL DEFERRED;');
        DB::table('simpeg_data_jabatan_struktural')->delete();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE;');

        // 1. Data referensi yang dibutuhkan
        $pegawaiList = SimpegPegawai::all()->keyBy('nip');
        $unitKerjaList = SimpegUnitKerja::all()->keyBy('kode_unit');
        $jenisJabatanList = JenisJabatanStruktural::all()->keyBy('kode');
        $defaultPangkat = SimpegMasterPangkat::first(); 
        $defaultEselon = SimpegEselon::first(); // Ambil eselon default

        // Pastikan data referensi utama ada
        if ($pegawaiList->isEmpty() || $unitKerjaList->isEmpty() || $jenisJabatanList->isEmpty() || !$defaultPangkat || !$defaultEselon) {
            $this->command->error('Tabel Pegawai, Unit Kerja, Jenis Jabatan Struktural, Master Pangkat, atau Eselon kosong. Jalankan seeder terkait terlebih dahulu.');
            return;
        }

        // 2. Definisikan dan buat Jabatan Struktural jika belum ada
        $this->command->info('Memastikan data master Jabatan Struktural tersedia...');
        
        $jabatanStrukturalDefinisi = [
            // [kode, singkatan, jenis_kode, unit_kerja_kode, parent_kode]
            ['10000', 'Rektor', '10000', '041001', null],
            ['12000', 'Dekan FT', '12000', '01', '10000'],
            ['12112', 'WD I FT', '12112', '01', '12000'],
            ['12120', 'WD II FT', '12120', '01', '12000'],
            ['12130', 'WD III FT', '12130', '01', '12000'],
            ['12110', 'Kaprodi TI', '12110', '86203', '12000'],
            ['12111', 'Sekprodi TI', '12111', '86203', '12110'],
            ['20000', 'Staff', '20000', '041001', null],
        ];

        foreach ($jabatanStrukturalDefinisi as $data) {
            $jenisJabatan = $jenisJabatanList->get($data[2]);
            $unitKerja = $unitKerjaList->get($data[3]);

            if ($jenisJabatan && $unitKerja) {
                SimpegJabatanStruktural::updateOrCreate(
                    ['kode' => $data[0]],
                    [
                        'singkatan' => $data[1],
                        'jenis_jabatan_struktural_id' => $jenisJabatan->id,
                        'unit_kerja_id' => $unitKerja->id,
                        'parent_jabatan' => $data[4],
                        'pangkat_id' => $defaultPangkat->id,
                        'eselon_id' => $defaultEselon->id, // FIX: Menambahkan eselon_id
                        'aktif' => true,
                        'is_pimpinan' => in_array($data[2], ['10000', '12000', '12110']),
                    ]
                );
            } else {
                $this->command->warn("Gagal membuat jabatan '{$data[1]}', jenis jabatan (kode: {$data[2]}) atau unit kerja (kode: {$data[3]}) tidak ditemukan.");
            }
        }
        $this->command->info('Data master Jabatan Struktural telah siap.');
        
        // Ambil kembali daftar jabatan yang sudah di-update/create
        $jabatanList = SimpegJabatanStruktural::all()->keyBy('kode');

        // 3. Mapping antara NIP pegawai dengan KODE jabatan struktural
        $jabatanMapping = [
            '196501011990031001' => '10000', // Prof. Dr. Sutrisno, M.Pd. -> Rektor
            '198505152010121002' => '12000', // Dr. Satria Sudirja, S.Kom., M.T. -> Dekan FT
            '197803102005012001' => '12112', // Dr. Siti Nurhasanah, S.E., M.M. -> Wakil Dekan I FT
            '198201152008011001' => '12120', // Ir. Ahmad Fauzi, M.T. -> Wakil Dekan II FT
            '198907122012012001' => '12130', // Dr. Rina Permatasari, S.Kom., M.Kom. -> Wakil Dekan III FT
            '199001011015011001' => '12110', // Budi Santoso, S.Kom., M.T. -> Ketua Prodi TI
            '199205102017012001' => '12111', // Ani Suryani, S.Kom. -> Sekretaris Prodi TI
            '199512152020011001' => '20000', // Muhammad Rizki, S.Kom. -> Staff
            '199001010001'       => '20000', // Administrator Sistem -> Staff
            '199001010002'       => '20000', // Super Admin -> Staff
        ];
        
        $this->command->info('Memulai seeding data jabatan struktural untuk pegawai...');
        $createdCount = 0;

        foreach ($jabatanMapping as $nip => $kodeJabatan) {
            $pegawai = $pegawaiList->get($nip);
            $jabatan = $jabatanList->get($kodeJabatan);

            if ($pegawai && $jabatan) {
                SimpegDataJabatanStruktural::create([
                    'pegawai_id'            => $pegawai->id,
                    'jabatan_struktural_id' => $jabatan->id,
                    'tgl_mulai'             => $faker->dateTimeBetween('-5 years', '-2 years')->format('Y-m-d'),
                    'tgl_selesai'           => null, // Jabatan aktif
                    'no_sk'                 => 'SK/' . $faker->numberBetween(100, 999) . '/YYS/' . date('Y'),
                    'tgl_sk'                => $faker->dateTimeThisYear()->format('Y-m-d'),
                    'pejabat_penetap'       => 'Rektor',
                    'tgl_input'             => now()->toDateString(),
                    'status_pengajuan'      => 'disetujui',
                ]);
                $createdCount++;
            } else {
                if (!$pegawai) $this->command->warn("Tidak dapat menemukan pegawai dengan NIP: {$nip}.");
                if (!$jabatan) $this->command->warn("Tidak dapat menemukan jabatan dengan KODE: {$kodeJabatan}.");
            }
        }

        $this->command->info("Berhasil membuat {$createdCount} data jabatan struktural.");
    }
}
