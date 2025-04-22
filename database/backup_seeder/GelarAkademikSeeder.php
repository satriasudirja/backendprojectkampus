<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GelarAkademikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data yang akan diisi ke tabel gelar_akademik
        $data = [
            ['gelar' => 'S.S.', 'nama_gelar' => 'Sarjana Sastra'],
            ['gelar' => 'S.H.', 'nama_gelar' => 'Sarjana Hukum'],
            ['gelar' => 'S.E.', 'nama_gelar' => 'Sarjana Ekonomi'],
            ['gelar' => 'S.IP', 'nama_gelar' => 'Sarjana Ilmu Politik'],
            ['gelar' => 'S.Sos', 'nama_gelar' => 'Sarjana Ilmu Sosial'],
            ['gelar' => 'S.Psi', 'nama_gelar' => 'Sarjana Psikologi'],
            ['gelar' => 'S.Ked', 'nama_gelar' => 'Sarjana Kedokteran'],
            ['gelar' => 'S.KM', 'nama_gelar' => 'Sarjana Kesehatan Masyarakat'],
            ['gelar' => 'S.KG', 'nama_gelar' => 'Sarjana Kedokteran Gigi'],
            ['gelar' => 'S.P', 'nama_gelar' => 'Sarjana Pertanian'],
            ['gelar' => 'S.TP', 'nama_gelar' => 'Sarjana Teknologi Pertanian'],
            ['gelar' => 'S.Pt', 'nama_gelar' => 'Sarjana Peternakan'],
            ['gelar' => 'S.Pi', 'nama_gelar' => 'Sarjana Perikanan'],
            ['gelar' => 'S.Hut', 'nama_gelar' => 'Sarjana Kehutanan'],
            ['gelar' => 'S.KH', 'nama_gelar' => 'Sarjana Kedokteran Hewan'],
            ['gelar' => 'S.Si', 'nama_gelar' => 'Sarjana Sains'],
            ['gelar' => 'S.T', 'nama_gelar' => 'Sarjana Teknik'],
            ['gelar' => 'S.Kom', 'nama_gelar' => 'Sarjana Komputer'],
            ['gelar' => 'S.Sn', 'nama_gelar' => 'Sarjana Seni'],
            ['gelar' => 'S.Pd', 'nama_gelar' => 'Sarjana Pendidikan'],
            ['gelar' => 'S.Ag', 'nama_gelar' => 'Sarjana Agama'],
            ['gelar' => 'M.Hum', 'nama_gelar' => 'Magister Humaniora'],
            ['gelar' => 'M.M.', 'nama_gelar' => 'Magister Manajemen'],
            ['gelar' => 'M.Si', 'nama_gelar' => 'Magister Sains'],
            ['gelar' => 'M.Kes', 'nama_gelar' => 'Magister Kesehatan'],
            ['gelar' => 'M.P', 'nama_gelar' => 'Magister Pertanian'],
            ['gelar' => 'M.T', 'nama_gelar' => 'Magister Teknik'],
            ['gelar' => 'M.Kom', 'nama_gelar' => 'Magister Komputer'],
            ['gelar' => 'M.Sn', 'nama_gelar' => 'Magister Seni'],
            ['gelar' => 'M.Pd', 'nama_gelar' => 'Magister Pendidikan'],
            ['gelar' => 'M.Ag', 'nama_gelar' => 'Magister Agama'],
            ['gelar' => 'Drs', 'nama_gelar' => 'Doktorandes'],
            ['gelar' => 'Dra', 'nama_gelar' => 'Doktoranda'],
            ['gelar' => 'Ir', 'nama_gelar' => 'Insinyur'],
            ['gelar' => 'Dr', 'nama_gelar' => 'Doktor'],
            ['gelar' => 'Prof', 'nama_gelar' => 'Profesor'],
            ['gelar' => 'Ph.D', 'nama_gelar' => 'Doctor of Philosophy'],
            ['gelar' => 'M.M.Pd', 'nama_gelar' => 'Magister Manajemen Pendidikan'],
            ['gelar' => 'S.Mn', 'nama_gelar' => 'Sarjana Manajemen'],
            ['gelar' => 'S.Pd.I', 'nama_gelar' => 'Sarjana Pendidikan Islam'],
            ['gelar' => 'S.Pd.Kom', 'nama_gelar' => 'Sarjana Pendidikan Komputer'],
            ['gelar' => 'S.Sy', 'nama_gelar' => 'Sarjana Syariah'],
            ['gelar' => 'S.Sos.I', 'nama_gelar' => 'Sarjana Sosial Islam'],
            ['gelar' => 'S.Th.I', 'nama_gelar' => 'Sarjana Theologi Islam'],
            ['gelar' => 'S.Pil.I', 'nama_gelar' => 'Sarjana Pilosophi Islam'],
            ['gelar' => 'S.Hum', 'nama_gelar' => 'Sarjana Humaniora'],
            ['gelar' => 'A.Md', 'nama_gelar' => 'Ahli Madya'],
            ['gelar' => 'A.Ma.Pd', 'nama_gelar' => 'Ahli Madya Pendidikan'],
            ['gelar' => 'B.A.', 'nama_gelar' => 'Bachelor of Art'],
            ['gelar' => 'Dipl.-Ing.', 'nama_gelar' => 'Diplom-Ingenieur'],
            ['gelar' => 'S.ST', 'nama_gelar' => 'Sarjana Sains Terapan'],
            ['gelar' => 'S.Kep', 'nama_gelar' => 'Sarjana Keperawatan'],
            ['gelar' => 'M.Kep', 'nama_gelar' => 'Magister Keperawatan'],
            ['gelar' => 'M.MKes', 'nama_gelar' => 'Magister Manajemen Kesehatan'],
            ['gelar' => 'M.P.h', 'nama_gelar' => 'Magister Public Hotel'],
            ['gelar' => 'A.Per.Pen', 'nama_gelar' => 'Ahli Perawat Pendidik'],
            ['gelar' => 'M.E', 'nama_gelar' => '-'],
            ['gelar' => 'M.Sos', 'nama_gelar' => '-'],
            ['gelar' => 'A.Md.Si', 'nama_gelar' => '-'],
            ['gelar' => 'A.Md.Kom', 'nama_gelar' => '-'],
            ['gelar' => 'S.Mat', 'nama_gelar' => '-'],
            ['gelar' => 'M.Mat', 'nama_gelar' => '-'],
            ['gelar' => 'S.Stat', 'nama_gelar' => '-'],
            ['gelar' => 'M.Stat', 'nama_gelar' => '-'],
            ['gelar' => 'A.Md.Pt', 'nama_gelar' => '-'],
            ['gelar' => 'M.Pt', 'nama_gelar' => '-'],
            ['gelar' => 'M.Pi', 'nama_gelar' => '-'],
            ['gelar' => 'A.Md.Pi', 'nama_gelar' => '-'],
            ['gelar' => 'S.Agr', 'nama_gelar' => '-'],
            ['gelar' => 'M.Agr', 'nama_gelar' => '-'],
            ['gelar' => 'M.P.W', 'nama_gelar' => '-'],
            ['gelar' => 'S.Ars', 'nama_gelar' => '-'],
            ['gelar' => 'M.Ars', 'nama_gelar' => '-'],
            ['gelar' => 'A.Md.Akun', 'nama_gelar' => '-'],
            ['gelar' => 'S.M', 'nama_gelar' => '-'],
            ['gelar' => 'M.M', 'nama_gelar' => '-'],
            ['gelar' => 'A.Md.T', 'nama_gelar' => '-'],
            ['gelar' => 'M.Ling', 'nama_gelar' => '-'],
            ['gelar' => 'M.Gz', 'nama_gelar' => '-'],
            ['gelar' => 'M.Vet', 'nama_gelar' => '-'],
            ['gelar' => 'S.K.H', 'nama_gelar' => '-'],
            ['gelar' => 'drh.', 'nama_gelar' => '-'],
            ['gelar' => 'S.Gz', 'nama_gelar' => '-'],
            ['gelar' => 'M.I.K', 'nama_gelar' => '-'],
            ['gelar' => 'M.Bitek', 'nama_gelar' => '-'],
            ['gelar' => 'S.Ds', 'nama_gelar' => '-'],
            ['gelar' => 'S.Tr.Ds', 'nama_gelar' => '-'],
            ['gelar' => 'M.Akt', 'nama_gelar' => '-'],
            ['gelar' => 'S.P.W', 'nama_gelar' => '-'],
            ['gelar' => 'M.Log', 'nama_gelar' => '-'],
            ['gelar' => 'M.A.B', 'nama_gelar' => '-'],
            ['gelar' => 'S.Bns', 'nama_gelar' => '-'],
            ['gelar' => 'M.Farm', 'nama_gelar' => '-'],
            ['gelar' => 'S.Farm', 'nama_gelar' => '-'],
            ['gelar' => 'Apt', 'nama_gelar' => '-'],
            ['gelar' => 'S.Bitek', 'nama_gelar' => '-'],
        ];

        // Insert data ke tabel gelar_akademik
        DB::table('gelar_akademik')->insert($data);
    }
}