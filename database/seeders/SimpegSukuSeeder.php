<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegSukuSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $sukuList = [
            'Aba', 'Akit', 'Alor', 'Amungme', 'Apokayan', 'Arfak', 'Asmat', 'Bajawa', 'Balangan', 'Balantak',
            'Banda', 'Banggai', 'Banjar', 'Batin', 'Bilida', 'Bima', 'Bolaang Mangondow', 'Bugis', 'Bukit',
            'Bulungan', 'Bungku', 'Buton', 'Butung', 'Dani', 'Dawan', 'Dayak', 'Deyah', 'Dompelas', 'Dompu',
            'Dongo', 'Dusun', 'Ende', 'Enggano', 'Gorontalo', 'Halmahera', 'Helong', 'Jagai', 'Jambi',
            'Kabaina', 'Kaili', 'Karimun', 'Kaur', 'Kayan', 'Kedang', 'Kei', 'Kemak', 'Kerinci', 'Kikim',
            'Komering', 'Kore', 'Krowe', 'Krui Abung', 'Kubu', 'Kulawi', 'Kutai', 'Lamaholot', 'Landawe',
            'Laut', 'Lawangan', 'Lembak', 'Lie', 'Lintang', 'Loda', 'Lore', 'Lun Bawang / Lun Dayeh',
            'Maamyan', 'Maanyan', 'Madura', 'Mamuju', 'Manggarai', 'Manyuke', 'Mata', 'Mbaluh', 'Mbojo',
            'Mekongga', 'Melayu', 'Melayu-Pontianak', 'Melus', 'Mori', 'Morotai', 'Muna', 'Murut', 'Musi',
            'Nage', 'Ngaju', 'Nimboran', 'Obi', 'Ogan', 'Orang utan Bonai', 'Ot Danum', 'Otdanum', 'Pamona',
            'Panukal', 'Pasemah', 'Pedah', 'Pegagah', 'Pekal', 'Penesek Gumay', 'Penghulu', 'Pubian',
            'Punan', 'Punau', 'Rawas', 'Rejang', 'Riung', 'Rote', 'Sa’dan', 'Sakai', 'Samawa', 'Sangiher Talaud',
            'Sangir', 'Sasak', 'Sekak Rambang', 'Semenda', 'Seputih', 'Seram', 'Serawai', 'Siak', 'Sikka',
            'Skadau', 'Suluan', 'Suluk', 'Sumba', 'Sunda', 'Sungkai', 'Talang Mamak', 'Tarlawi', 'Tatum',
            'Tengger', 'Ternate', 'Tidore', 'Tidung', 'Togite', 'Tolaiwiw', 'Tolaki', 'Toli-toli', 'Tomini',
            'Toraja', 'Tulang Bawang', 'Ulu Aer', 'Wolio', 'Mandar', 'Aceh', 'Alas', 'Ambon', 'Anak Jame',
            'Bacan', 'Baduy', 'Bajau', 'Balatar', 'Bali', 'Bangka', 'Banten', 'Batak Angkola', 'Batak Fakfak',
            'Batak Karo', 'Batak Mandailing', 'Batak Simalungun', 'Batak Toba', 'Betawi', 'Bunoi', 'Buol',
            'Buru', 'Caniago', 'Flores', 'Gayo', 'Gusci', 'Jawa', 'Kapuas', 'Katingan', 'Kato', 'Kayau',
            'Kluet', 'Makassar', 'Mamasa', 'Mapute', 'Maya-maya', 'Mentawai', 'Mey Brat', 'Minahasa',
            'Minangkabau', 'Muko-muko', 'Nias', 'Osing', 'Panyali', 'Pesisir', 'Pulau', 'Ranau', 'Sabu',
            'Samin', 'Sentani', 'Sikumbang', 'Simeleuw', 'Singkil', 'Suku', 'Tamiang', 'Tanjung', 'Tionghoa',
            'Tobati'
        ];

        $data = array_map(function ($suku) use ($now) {
            return [
                'nama_suku' => $suku,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $sukuList);

        DB::table('simpeg_suku')->insert($data);
    }
}
