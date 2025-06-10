<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SimpegMasterPangkat;

class SimpegJabatanStrukturalSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        // Pastikan unit kerja Universitas Ibn Khaldun ada (ID 1)
        $unitKerjaId = DB::table('simpeg_unit_kerja')->where('nama_unit', 'Universitas Ibn Khaldun')->value('id');
        if (!$unitKerjaId) {
            $unitKerjaId = 1; // Default fallback
        }

        // Get pangkat references - assign appropriate pangkat for each position level
        $pangkatPembina = SimpegMasterPangkat::where('pangkat', 'IV/a')->first(); // Pembina - for Rektor level
        $pangkatPenata = SimpegMasterPangkat::where('pangkat', 'III/d')->first(); // Penata Tingkat I - for Wakil/Kepala level  
        $pangkatPengatur = SimpegMasterPangkat::where('pangkat', 'III/c')->first(); // Penata - for middle management
        $pangkatStaf = SimpegMasterPangkat::where('pangkat', 'III/a')->first(); // Penata Muda - for staff level

        // Fallback to first available pangkat if specific ones don't exist
        if (!$pangkatPembina) $pangkatPembina = SimpegMasterPangkat::first();
        if (!$pangkatPenata) $pangkatPenata = SimpegMasterPangkat::first();
        if (!$pangkatPengatur) $pangkatPengatur = SimpegMasterPangkat::first();
        if (!$pangkatStaf) $pangkatStaf = SimpegMasterPangkat::first();

        // Get eselon references - assign appropriate eselon for each position level
        $eselonI = DB::table('simpeg_eselon')->where('nama_eselon', 'IA')->first(); // Eselon I - for Rektor level
        $eselonII = DB::table('simpeg_eselon')->where('nama_eselon', 'IIA')->first(); // Eselon II - for Wakil Rektor, Dekan
        $eselonIII = DB::table('simpeg_eselon')->where('nama_eselon', 'IIIA')->first(); // Eselon III - for Kepala Biro, Wakil Dekan
        $eselonIV = DB::table('simpeg_eselon')->where('nama_eselon', 'IVA')->first(); // Eselon IV - for Kepala Bagian, Staff

        // Fallback to first available eselon if specific ones don't exist
        if (!$eselonI) $eselonI = DB::table('simpeg_eselon')->first();
        if (!$eselonII) $eselonII = DB::table('simpeg_eselon')->first();
        if (!$eselonIII) $eselonIII = DB::table('simpeg_eselon')->first();
        if (!$eselonIV) $eselonIV = DB::table('simpeg_eselon')->first();

        $data = [
            // Level Universitas - High level positions get higher eselon
            ['kode' => '001', 'singkatan' => 'Rektor', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 1, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPembina->id, 'eselon_id' => $eselonI->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '002', 'singkatan' => 'Wakil Rektor Bidang Akademik', 'parent_jabatan' => '001', 'jenis_jabatan_struktural_id' => 2, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '003', 'singkatan' => 'Wakil Rektor Bidang Pengelolaan Sumberdaya', 'parent_jabatan' => '001', 'jenis_jabatan_struktural_id' => 2, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '004', 'singkatan' => 'Wakil Rektor Bidang Kemahasiswaan dan Dakwah', 'parent_jabatan' => '001', 'jenis_jabatan_struktural_id' => 2, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '005', 'singkatan' => 'Wakil Rektor Bidang Kerjasama, Inovasi dan Pengembangan', 'parent_jabatan' => '001', 'jenis_jabatan_struktural_id' => 2, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '006', 'singkatan' => 'Sekretaris Rektor', 'parent_jabatan' => '001', 'jenis_jabatan_struktural_id' => 16, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '007', 'singkatan' => 'Staf Ahli Rektor Bidang Akademik dan Publikasi Ilmiah', 'parent_jabatan' => '002', 'jenis_jabatan_struktural_id' => 17, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '008', 'singkatan' => 'Staf Ahli Rektor Bidang Kemahasiswaan, Kerjasama dan Dakwah', 'parent_jabatan' => '004', 'jenis_jabatan_struktural_id' => 17, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],

            // Level Biro dan Lembaga - Middle management level
            ['kode' => '009', 'singkatan' => 'Kepala Biro Administrasi Akademik dan Kemahasiswaan', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 7, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '010', 'singkatan' => 'Kepala Bagian Adminstrasi Pendidikan', 'parent_jabatan' => '009', 'jenis_jabatan_struktural_id' => 20, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '011', 'singkatan' => 'Kepala Bagian Adminstrasi Kemahasiswaan dan Hubungan Alumni', 'parent_jabatan' => '009', 'jenis_jabatan_struktural_id' => 20, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '018', 'singkatan' => 'Kepala Biro Administarsi Keuangan dan Kerjasama', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 7, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '029', 'singkatan' => 'Kepala Lembaga Penelitian dan Pengabdian Kepada Masyarakat', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 7, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '034', 'singkatan' => 'Kepala UPT Perpustakaan', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 8, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '040', 'singkatan' => 'Ketua Kantor Penjaminan Mutu dan Audit Internal', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 8, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],

            // Level Fakultas - Faculty level management
            ['kode' => '052', 'singkatan' => 'Dekan', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 9, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '053', 'singkatan' => 'Wakil Dekan Bidang Akademik', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 12, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '054', 'singkatan' => 'Wakil Dekan Bidang Pengelolaan Sumberdaya', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 13, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '055', 'singkatan' => 'Wakil Dekan Bidang Kemahasiswaan, Kerjasama dan Dakwah', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 14, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '056', 'singkatan' => 'Ketua Program Studi', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 11, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '057', 'singkatan' => 'Sekretaris Program Studi', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 12, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatStaf->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '058', 'singkatan' => 'Kepala Laboratorium', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 15, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatStaf->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '059', 'singkatan' => 'Ketua Gugus Penjaminan Mutu', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 17, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatStaf->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '060', 'singkatan' => 'Kepala Bagian Tata Usaha', 'parent_jabatan' => '052', 'jenis_jabatan_struktural_id' => 20, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatStaf->id, 'eselon_id' => $eselonIV->id, 'is_pimpinan' => false, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],

            // Level Pascasarjana - Graduate school level
            ['kode' => '070', 'singkatan' => 'Direktur Sekolah Pascasarjana', 'parent_jabatan' => null, 'jenis_jabatan_struktural_id' => 6, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPenata->id, 'eselon_id' => $eselonII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '071', 'singkatan' => 'Wakil Direktur Bidang Akademik, Inovasi, dan Kemahasiswaan', 'parent_jabatan' => '070', 'jenis_jabatan_struktural_id' => 12, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kode' => '072', 'singkatan' => 'Wakil Direktur Bidang Sumberdaya, Kerjasama dan Dakwah', 'parent_jabatan' => '070', 'jenis_jabatan_struktural_id' => 13, 'unit_kerja_id' => $unitKerjaId, 'pangkat_id' => $pangkatPengatur->id, 'eselon_id' => $eselonIII->id, 'is_pimpinan' => true, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('simpeg_jabatan_struktural')->insert($data);
    }
}