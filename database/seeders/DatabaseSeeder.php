<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            //   SimpegJabatanStrukturalSeeder::class,
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
            SimpegMasterOutputPenelitianSeeder::class,

<<<<<<< HEAD
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
    CaptchaPuzzleSeeder::class,
    SimpegKategoriSertifikasiSeeder::class,

=======
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
>>>>>>> 4d2573d (push satria)

            SimpegHubunganKerjaSeeder::class,
            SimpegJenisHariSeeder::class,
            SimpegBeritaSeeder::class,
            SimpegMasterPerguruanTinggiSeeder::class,
            SimpegMasterProdiPerguruanTinggiSeeder::class,
            CaptchaPuzzleSeeder::class,
            SimpegMasterGelarAkademikSeeder::class,
            SimpegDataPendidikanFormalSeeder::class,
            SimpegJabatanFungsionalSeeder::class,
            SimpegDataJabatanFungsionalSeeder::class,
            SimpegDataJabatanStrukturalSeeder::class,
        ]);
    }
}
