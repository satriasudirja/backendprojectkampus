<?php

namespace Database\Seeders;

use App\Models\SimpegDaftarCuti;
use App\Models\SimpegDaftarJenisSk;
use App\Models\SimpegDaftarJenisTest;
use App\Models\MasterPerguruanTinggi;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
    SimpegUserRoleSeeder::class, 
    SimpegJabatanAkademikSeeder::class,  
    SimpegStatusPernikahanSeeder::class,
    SimpegSukuSeeder::class,
    SimpegUnitKerjaSeeder::class,
    SimpegStatusAktifSeeder::class,
    RumpunBidangIlmuSeeder::class,

    SimpegPegawaiSeeder::class,
    SimpegBahasaSeeder::class,
    SimpegJenisCutiSeeder::class,
    SimpegDaftarJenisLuaranSeeder::class,
    PkmSeeder::class,
    SimpegDaftarJenisSkSeeder::class,
    SimpegDaftarJenisTestSeeder::class,
    SimpegDaftarJenisSkSeeder::class,
    SimpegDaftarJenisTestSeeder::class,
    SimpegMasterOutputPenelitianSeeder::class,

    SimpegJenisJabatanStrukturalSeeder::class,
    SimpegMasterPangkatSeeder::class,
    SimpegEselonSeeder::class,
    SimpegJabatanStrukturalSeeder::class,
    SimpegJamKerjaSeeder::class,
    SimpegMasterJenisSertifikasiSeeder::class,
    SimpegDataRiwayatPekerjaanSeeder::class,
    SimpegUnivLuarSeeder::class,
    SimpegJenjangPendidikanSeeder::class,
    SimpegDataSertifikasiSeeder::class,


    SimpegHubunganKerjaSeeder::class,
    SimpegJenisHariSeeder::class,
    SimpegBeritaSeeder::class,
    SimpegMasterPerguruanTinggiSeeder::class,
    SimpegMasterProdiPerguruanTinggiSeeder::class,
   

        ]);

    }
}