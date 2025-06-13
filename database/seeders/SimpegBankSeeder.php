<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimpegBank;

class SimpegBankSeeder extends Seeder
{
    public function run()
    {
        $banks = [
            ['kode' => 'BAS', 'nama_bank' => 'Bank Aceh Syariah'],
            ['kode' => 'BCA', 'nama_bank' => 'Bank BCA'],
            ['kode' => 'BKN', 'nama_bank' => 'BUKOPIN'],
            ['kode' => 'BNI', 'nama_bank' => 'Bank BNI'],
            ['kode' => 'BRI', 'nama_bank' => 'Bank BRI'],
            ['kode' => 'BSI', 'nama_bank' => 'Bank Syariah Indonesia'],
            ['kode' => 'BSM', 'nama_bank' => 'Bank Syariah Mandiri'],
            ['kode' => 'MAN', 'nama_bank' => 'Bank Mandiri'],
        ];

        foreach ($banks as $bank) {
            // Menggunakan updateOrCreate agar seeder aman dijalankan berkali-kali
            SimpegBank::updateOrCreate(
                ['kode' => $bank['kode']],
                $bank
            );
        }

        $this->command->info('Tabel simpeg_bank berhasil di-seed.');
    }
}