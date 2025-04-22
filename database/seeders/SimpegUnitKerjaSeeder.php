<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegUnitKerja;
use Illuminate\Support\Str;

class SimpegUnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        $data = [
            ['kode_unit' => '041001', 'nama_unit' => 'Universitas Ibn Khaldun', 'parent' => null],
            ['kode_unit' => '01', 'nama_unit' => 'Fakultas Keguruan dan Ilmu Pendidikan', 'parent' => '041001'],
            ['kode_unit' => '82119', 'nama_unit' => 'Pendidikan Vokasional Desain Fashion', 'parent' => '01'],
            ['kode_unit' => '84202', 'nama_unit' => 'Pendidikan Matematika', 'parent' => '01'],
            ['kode_unit' => '86203', 'nama_unit' => 'Teknologi Pendidikan', 'parent' => '01'],
            ['kode_unit' => '86227', 'nama_unit' => 'Pendidikan Masyarakat', 'parent' => '01'],
            ['kode_unit' => '86906', 'nama_unit' => 'Pendidikan Profesi Guru', 'parent' => '01'],
            ['kode_unit' => '88203', 'nama_unit' => 'Pendidikan Bahasa Inggris', 'parent' => '01'],
            ['kode_unit' => '02', 'nama_unit' => 'Fakultas Hukum', 'parent' => '041001'],
            ['kode_unit' => '74201', 'nama_unit' => 'Ilmu Hukum', 'parent' => '02'],
            // ... Tambahkan semua unit lain dari list kamu di sini ...
            ['kode_unit' => 'AU0102', 'nama_unit' => 'Bank Amanah Ummah', 'parent' => '041001'],
        ];

        foreach ($data as $unit) {
            SimpegUnitKerja::create([
                'kode_unit' => $unit['kode_unit'],
                'nama_unit' => $unit['nama_unit'],
                'parent_unit_id' => $unit['parent'],
                'jenis_unit_id' => rand(1, 5),
                'tk_pendidikan_id' => rand(1, 3),
                'alamat' => $faker->address,
                'telepon' => $faker->phoneNumber,
                'website' => $faker->url,
                'alamat_email' => $faker->unique()->safeEmail,
                'akreditasi_id' => rand(1, 4),
                'no_sk_akreditasi' => strtoupper(Str::random(10)),
                'tanggal_akreditasi' => $faker->date(),
                'no_sk_pendirian' => strtoupper(Str::random(10)),
                'tanggal_sk_pendirian' => $faker->date(),
                'gedung' => 'Gedung ' . strtoupper(Str::random(1)),
            ]);
        }
    }
}
