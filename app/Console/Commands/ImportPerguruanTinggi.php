<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\MasterPerguruanTinggi;

class ImportPerguruanTinggi extends Command
{
    protected $signature = 'import:perguruan-tinggi';
    protected $description = 'Import data perguruan tinggi dari Kampus GraphQL API ke tabel simpeg_master_perguruan_tinggi';

    public function handle()
    {
        $response = Http::post('http://localhost:3300/graphql', [
            'query' => '
                query {
                    universities {
                        id
                        name
                        address
                        phone
                    }
                }
            '
        ]);

        // Periksa status kode HTTP
        if ($response->status() !== 200) {
            $this->error('Gagal mengambil data dari API. Status code: ' . $response->status());
            return;
        }

        // Cek apakah response berhasil
        $universities = $response->json('data.universities');

        if (!$universities) {
            $this->error('Tidak ada data universitas ditemukan.');
            return;
        }

        foreach ($universities as $university) {
            MasterPerguruanTinggi::updateOrCreate(
                ['kode' => $university['id']], // id API jadi kode di DB
                [
                    'nama_universitas' => $university['name'],
                    'alamat' => $university['address'] ?? '',
                    'no_telp' => $university['phone'] ?? '',
                ]
            );
        }

        $this->info('Data perguruan tinggi berhasil diimport!');
    }
}
