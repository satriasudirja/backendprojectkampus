<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Model diupdate sesuai referensi yang diberikan
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegDataJabatanAkademik;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegDataHubunganKerja;
use App\Models\SimpegDataSertifikasi;
use App\Models\SimpegDataTes;
use App\Models\SimpegDataPenghargaan;
use App\Models\SimpegDataOrganisasi;
use App\Models\SimpegDataKemampuanBahasa;


class MonitoringRiwayatController extends Controller
{
    /**
     * Mengambil data rekapitulasi riwayat untuk monitoring.
     * Data dikelompokkan berdasarkan status: 'draft', 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan'.
     * Data yang diambil adalah milik dosen yang sedang login.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Mendapatkan ID pegawai (dosen) yang sedang login
        $pegawaiId = Auth::id();

        if (!$pegawaiId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated. Tidak dapat menemukan ID pegawai.'
            ], 401);
        }

        // Status yang akan dihitung
        $statuses = ['draft', 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan'];

        // Nama kolom status yang benar
        $statusColumn = 'status_pengajuan';

        // Helper function untuk membuat query dasar
        $buildQuery = function ($modelClass) use ($pegawaiId, $statuses, $statusColumn) {
            $table = (new $modelClass)->getTable();
            return DB::table($table)
                ->select($statusColumn, DB::raw("COUNT(*) as count"))
                ->where('pegawai_id', $pegawaiId)
                ->whereIn($statusColumn, $statuses)
                ->groupBy($statusColumn);
        };

        // Definisikan model untuk setiap kategori dan item, disesuaikan dengan referensi
        $dataMapping = [
            'kepegawaian' => [
                'Pangkat' => \App\Models\SimpegDataPangkat::class,
                'Jabatan Akademik' => \App\Models\SimpegDataJabatanAkademik::class,
                'Jabatan Fungsional' => \App\Models\SimpegDataJabatanFungsional::class,
                'Jabatan Struktural' => \App\Models\SimpegDataJabatanStruktural::class,
                'Hubungan Kerja' => \App\Models\SimpegDataHubunganKerja::class,
            ],
            'kompetensi' => [
                'Sertifikasi' => \App\Models\SimpegDataSertifikasi::class,
                'Tes' => \App\Models\SimpegDataTes::class,
            ],
            'penunjang' => [
                'Penghargaan' => \App\Models\SimpegDataPenghargaan::class,
            ],
            'pengembangan' => [
                'Organisasi' => \App\Models\SimpegDataOrganisasi::class,
                'Kemampuan Bahasa' => \App\Models\SimpegDataKemampuanBahasa::class,
            ]
        ];

        // Inisialisasi struktur data hasil
        $results = [];
        $overallTotals = array_fill_keys($statuses, 0);
        $overallTotals['total_keseluruhan'] = 0;

        // --- Proses Data Keluarga (Kasus Khusus, sesuai referensi) ---
        if (class_exists(\App\Models\SimpegDataKeluargaPegawai::class)) {
            // Menggunakan CASE untuk menentukan tipe keluarga dari satu tabel.
            $keluargaQuery = DB::table((new \App\Models\SimpegDataKeluargaPegawai)->getTable())
                ->select(
                    $statusColumn,
                    DB::raw('COUNT(*) as count'),
                    DB::raw("CASE
                        WHEN anak_ke IS NOT NULL THEN 'Anak'
                        WHEN nama_pasangan IS NOT NULL THEN 'Pasangan'
                        WHEN status_orangtua IS NOT NULL THEN 'Orang Tua'
                        ELSE 'Lainnya' END AS family_type")
                )
                ->where('pegawai_id', $pegawaiId)
                ->whereIn($statusColumn, $statuses)
                ->groupBy($statusColumn, 'family_type')
                ->get();

            $keluargaCounts = [
                'Pasangan' => array_fill_keys($statuses, 0),
                'Anak' => array_fill_keys($statuses, 0),
                'Orang Tua' => array_fill_keys($statuses, 0),
            ];

            foreach ($keluargaQuery as $item) {
                $label = $item->family_type;
                if (isset($keluargaCounts[$label])) {
                    // Gunakan properti yang benar dari objek hasil query
                    $keluargaCounts[$label][$item->{$statusColumn}] = $item->count;
                }
            }
            
            foreach ($keluargaCounts as $item => $counts) {
                if ($item === 'Lainnya') continue;

                $total = array_sum($counts);
                $formattedItem = [
                    'category' => 'keluarga',
                    'item' => str_replace(' ', '_', strtolower($item)),
                    'item_display' => $item,
                ];
                $formattedItem = array_merge($formattedItem, $counts);
                $formattedItem['total'] = $total;
                $results[] = $formattedItem;

                foreach($counts as $status => $count) {
                    $overallTotals[$status] += $count;
                }
                $overallTotals['total_keseluruhan'] += $total;
            }
        }

        // --- Proses Kategori Lainnya ---
        foreach ($dataMapping as $category => $items) {
            foreach ($items as $itemLabel => $modelClass) {
                if (!class_exists($modelClass)) continue;

                $query = $buildQuery($modelClass);
                $statusCounts = $query->get()->pluck('count', $statusColumn);

                $itemData = array_fill_keys($statuses, 0);
                foreach ($statusCounts as $status => $count) {
                    $itemData[$status] = $count;
                }

                $total = array_sum($itemData);
                $formattedItem = [
                    'category' => $category,
                    'item' => str_replace(' ', '_', strtolower($itemLabel)),
                    'item_display' => $itemLabel,
                ];
                $formattedItem = array_merge($formattedItem, $itemData);
                $formattedItem['total'] = $total;
                $results[] = $formattedItem;
                
                foreach($itemData as $status => $count) {
                    $overallTotals[$status] += $count;
                }
                $overallTotals['total_keseluruhan'] += $total;
            }
        }
        
        // Mengganti nama key dari status pengajuan ke status yang diminta di frontend
        $finalResults = [];
        foreach($results as $res) {
            $newItem = [];
            foreach($res as $key => $value) {
                if ($key === 'status_pengajuan') { // Ganti nama key jika ada
                    $newItem['status'] = $value;
                } else {
                    $newItem[$key] = $value;
                }
            }
            // Pastikan semua status ada di hasil akhir meskipun nilainya 0
            foreach($statuses as $s) {
                if (!isset($newItem[$s])) {
                    $newItem[$s] = $res[$s] ?? 0;
                }
            }
            $finalResults[] = $newItem;
        }

        return response()->json([
            'status' => 'success',
            'data' => $finalResults,
            'overall_totals' => $overallTotals,
        ]);
    }
}
