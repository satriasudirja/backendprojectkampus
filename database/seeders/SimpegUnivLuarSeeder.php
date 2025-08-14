<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpegUnivLuarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Data universitas luar negeri
        $universitasLuar = [
            [
                'id' => Str::uuid(),
                'kode' => 'HARVARD',
                'nama_universitas' => 'Harvard University',
                'alamat' => 'Cambridge, Massachusetts 02138, USA',
                'no_telp' => '+1-617-495-1000',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'MIT',
                'nama_universitas' => 'Massachusetts Institute of Technology',
                'alamat' => '77 Massachusetts Ave, Cambridge, MA 02139, USA',
                'no_telp' => '+1-617-253-1000',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'OXFORD',
                'nama_universitas' => 'University of Oxford',
                'alamat' => 'Oxford OX1 2JD, United Kingdom',
                'no_telp' => '+44-1865-270000',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'CAMBR',
                'nama_universitas' => 'University of Cambridge',
                'alamat' => 'The Old Schools, Trinity Ln, Cambridge CB2 1TN, UK',
                'no_telp' => '+44-1223-337733',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'STANF',
                'nama_universitas' => 'Stanford University',
                'alamat' => '450 Serra Mall, Stanford, CA 94305, USA',
                'no_telp' => '+1-650-723-2300',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'CALTECH',
                'nama_universitas' => 'California Institute of Technology',
                'alamat' => '1200 E California Blvd, Pasadena, CA 91125, USA',
                'no_telp' => '+1-626-395-6811',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'NUS',
                'nama_universitas' => 'National University of Singapore',
                'alamat' => '21 Lower Kent Ridge Rd, Singapore 119077',
                'no_telp' => '+65-6516-6666',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'UTokyo',
                'nama_universitas' => 'University of Tokyo',
                'alamat' => '7 Chome-3-1 Hongo, Bunkyo City, Tokyo 113-8654, Japan',
                'no_telp' => '+81-3-3812-2111',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'TU',
                'nama_universitas' => 'Tsinghua University',
                'alamat' => '30 Shuangqing Rd, Haidian District, Beijing, China',
                'no_telp' => '+86-10-62793001',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'ETH',
                'nama_universitas' => 'ETH Zurich',
                'alamat' => 'Rämistrasse 101, 8092 Zürich, Switzerland',
                'no_telp' => '+41-44-632-11-11',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'UCL',
                'nama_universitas' => 'University College London',
                'alamat' => 'Gower St, London WC1E 6BT, UK',
                'no_telp' => '+44-20-7679-2000',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'UMelb',
                'nama_universitas' => 'University of Melbourne',
                'alamat' => 'Parkville VIC 3010, Australia',
                'no_telp' => '+61-3-9035-5511',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'UBC',
                'nama_universitas' => 'University of British Columbia',
                'alamat' => 'Vancouver, BC V6T 1Z4, Canada',
                'no_telp' => '+1-604-822-2211',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'PSL',
                'nama_universitas' => 'PSL University',
                'alamat' => '60 Rue Mazarine, 75006 Paris, France',
                'no_telp' => '+33-1-85-76-08-70',
            ],
            [
                'id' => Str::uuid(),
                'kode' => 'LMU',
                'nama_universitas' => 'Ludwig Maximilian University of Munich',
                'alamat' => 'Geschwister-Scholl-Platz 1, 80539 München, Germany',
                'no_telp' => '+49-89-2180-0',
            ],
        ];

        // Tambahkan timestamps
        foreach ($universitasLuar as &$universitas) {
            $universitas['created_at'] = Carbon::now();
            $universitas['updated_at'] = Carbon::now();
        }

        // Insert data ke database
        DB::table('simpeg_univ_luar')->insert($universitasLuar);
    }
}