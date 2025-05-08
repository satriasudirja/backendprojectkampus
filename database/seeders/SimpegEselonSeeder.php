<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegEselon;

class SimpegEselonSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode' => '1I', 'nama_eselon' => 'IA', 'status' => true],
            ['kode' => '12', 'nama_eselon' => 'IB', 'status' => true],
            ['kode' => '13', 'nama_eselon' => 'IC', 'status' => true],
            ['kode' => '21', 'nama_eselon' => 'IIA', 'status' => true],
            ['kode' => '22', 'nama_eselon' => 'IIB', 'status' => true],
            ['kode' => '23', 'nama_eselon' => 'IIC', 'status' => true],
            ['kode' => '31', 'nama_eselon' => 'IIIA', 'status' => true],
            ['kode' => '32', 'nama_eselon' => 'IIIB', 'status' => true],
            ['kode' => '33', 'nama_eselon' => 'IIIC', 'status' => true],
            ['kode' => '34', 'nama_eselon' => 'IIID', 'status' => true],
            ['kode' => '41', 'nama_eselon' => 'IVA', 'status' => true],
            ['kode' => '42', 'nama_eselon' => 'IVB', 'status' => true],
            ['kode' => '43', 'nama_eselon' => 'IVC', 'status' => true],
            ['kode' => '44', 'nama_eselon' => 'IVD', 'status' => true],
        ];

        foreach ($data as $item) {
            SimpegEselon::create($item);
        }
    }
}