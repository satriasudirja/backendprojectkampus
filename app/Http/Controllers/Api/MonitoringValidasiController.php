<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
use App\Models\SimpegPegawai; // Pastikan model ini diimport
use Illuminate\Support\Facades\DB;

class MonitoringValidasiController extends Controller
{
    /**
     * Mengambil data rekapitulasi untuk monitoring validasi pegawai.
     * Filter berdasarkan status: 'diajukan', 'disetujui', 'ditolak', 'ditangguhkan' (jika ada).
     * Dapat juga difilter berdasarkan pegawai_id.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Mendapatkan status dari request, default ke semua status jika tidak ada
        $statusFilter = $request->input('status', ['diajukan', 'disetujui', 'ditolak', 'ditangguhkan']);
        if (!is_array($statusFilter)) {
            $statusFilter = explode(',', $statusFilter);
        }

        // Mendapatkan pegawai_id dari request, jika ada
        $pegawaiId = $request->input('pegawai_id');

        // Helper function to apply common filters (status and pegawai_id)
        $applyFilters = function ($query, $statusFilter, $pegawaiId) {
            $query->whereIn('status_pengajuan', (array)$statusFilter);
            if ($pegawaiId) {
                $query->where('pegawai_id', $pegawaiId);
            }
            return $query;
        };

        // Initialize data structure
        $data = [
            'total' => [
                'diajukan' => 0,
                'disetujui' => 0,
                'ditolak' => 0,
                'ditangguhkan' => 0, // Ditangguhkan tidak ada di semua model, akan dihitung jika ada
            ],
            'keluarga' => [
                'Pasangan' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Anak' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Orang Tua' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
            ],
            'kepegawaian' => [
                'Pangkat' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Jabatan Akademik' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Jabatan Fungsional' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Jabatan Struktural' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Hubungan Kerja' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
            ],
            'kompetensi' => [
                'Sertifikasi' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Tes' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
            ],
            'penunjang' => [
                'Penghargaan' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
            ],
            'pengembangan' => [
                'Organisasi' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
                'Kemampuan Bahasa' => [
                    'diajukan' => 0,
                    'disetujui' => 0,
                    'ditolak' => 0,
                    'ditangguhkan' => 0,
                ],
            ],
        ];

        // --- Data Keluarga ---
        $keluargaQuery = SimpegDataKeluargaPegawai::select('status_pengajuan',
            DB::raw('COUNT(*) as count'),
            DB::raw("CASE
                WHEN anak_ke IS NOT NULL THEN 'Anak'
                WHEN nama_pasangan IS NOT NULL THEN 'Pasangan'
                WHEN status_orangtua IS NOT NULL THEN 'Orang Tua'
                ELSE 'Lainnya' END AS family_type")
            );
        $keluargaQuery = $applyFilters($keluargaQuery, $statusFilter, $pegawaiId);
        $keluargaStatuses = $keluargaQuery->groupBy('status_pengajuan', 'family_type')->get();

        foreach ($keluargaStatuses as $item) {
            $formattedFamilyType = str_replace(' ', '_', strtolower($item->family_type));
            if (isset($data['keluarga'][$item->family_type][$item->status_pengajuan])) {
                $data['keluarga'][$item->family_type][$item->status_pengajuan] = $item->count;
                $data['total'][$item->status_pengajuan] += $item->count;
            }
        }

        // --- Data Kepegawaian ---
        $modelsKepegawaian = [
            'Pangkat' => SimpegDataPangkat::class,
            'Jabatan Akademik' => SimpegDataJabatanAkademik::class,
            'Jabatan Fungsional' => SimpegDataJabatanFungsional::class,
            'Jabatan Struktural' => SimpegDataJabatanStruktural::class,
            'Hubungan Kerja' => SimpegDataHubunganKerja::class,
        ];

        foreach ($modelsKepegawaian as $label => $modelClass) {
            $table = (new $modelClass)->getTable();
            $statusesQuery = DB::table($table)
                        ->select('status_pengajuan', DB::raw('COUNT(*) as count'));

            if ($pegawaiId) {
                $statusesQuery->where('pegawai_id', $pegawaiId);
            }
            $statuses = $statusesQuery->whereIn('status_pengajuan', $statusFilter)
                        ->groupBy('status_pengajuan')
                        ->get();

            foreach ($statuses as $status) {
                if (isset($data['kepegawaian'][$label][$status->status_pengajuan])) {
                    $data['kepegawaian'][$label][$status->status_pengajuan] = $status->count;
                    $data['total'][$status->status_pengajuan] += $status->count;
                }
            }
        }

        // --- Data Kompetensi ---
        $modelsKompetensi = [
            'Sertifikasi' => SimpegDataSertifikasi::class,
            'Tes' => SimpegDataTes::class,
        ];

        foreach ($modelsKompetensi as $label => $modelClass) {
            $table = (new $modelClass)->getTable();
            $statusesQuery = DB::table($table)
                        ->select('status_pengajuan', DB::raw('COUNT(*) as count'));

            if ($pegawaiId) {
                $statusesQuery->where('pegawai_id', $pegawaiId);
            }
            $statuses = $statusesQuery->whereIn('status_pengajuan', $statusFilter)
                        ->groupBy('status_pengajuan')
                        ->get();

            foreach ($statuses as $status) {
                if (isset($data['kompetensi'][$label][$status->status_pengajuan])) {
                    $data['kompetensi'][$label][$status->status_pengajuan] = $status->count;
                    $data['total'][$status->status_pengajuan] += $status->count;
                }
            }
        }

        // --- Data Penunjang ---
        $modelsPenunjang = [
            'Penghargaan' => SimpegDataPenghargaan::class,
        ];

        foreach ($modelsPenunjang as $label => $modelClass) {
            $table = (new $modelClass)->getTable();
            $statusesQuery = DB::table($table)
                        ->select('status_pengajuan', DB::raw('COUNT(*) as count'));

            // The SimpegDataPenghargaan model has 'pegawai_id' in its fillable.
            // If it doesn't, this line should be removed or adapted.
            if ($pegawaiId) {
                $statusesQuery->where('pegawai_id', $pegawaiId);
            }
            $statuses = $statusesQuery->whereIn('status_pengajuan', $statusFilter)
                        ->groupBy('status_pengajuan')
                        ->get();

            foreach ($statuses as $status) {
                if (isset($data['penunjang'][$label][$status->status_pengajuan])) {
                    $data['penunjang'][$label][$status->status_pengajuan] = $status->count;
                    $data['total'][$status->status_pengajuan] += $status->count;
                }
            }
        }

        // --- Data Pengembangan ---
        $modelsPengembangan = [
            'Organisasi' => SimpegDataOrganisasi::class,
            'Kemampuan Bahasa' => SimpegDataKemampuanBahasa::class,
        ];

        foreach ($modelsPengembangan as $label => $modelClass) {
            $table = (new $modelClass)->getTable();
            $statusesQuery = DB::table($table)
                        ->select('status_pengajuan', DB::raw('COUNT(*) as count'));

            if ($pegawaiId) {
                $statusesQuery->where('pegawai_id', $pegawaiId);
            }
            $statuses = $statusesQuery->whereIn('status_pengajuan', $statusFilter)
                        ->groupBy('status_pengajuan')
                        ->get();

            foreach ($statuses as $status) {
                if (isset($data['pengembangan'][$label][$status->status_pengajuan])) {
                    $data['pengembangan'][$label][$status->status_pengajuan] = $status->count;
                    $data['total'][$status->status_pengajuan] += $status->count;
                }
            }
        }

        // Format response sesuai dengan kebutuhan tampilan
        $formattedData = [];

        // Keluarga
        foreach ($data['keluarga'] as $type => $counts) {
            $formattedData[] = [
                'category' => 'keluarga', // lowercase
                'item' => str_replace(' ', '_', strtolower($type)), // lowercase and underscore
                'diajukan' => $counts['diajukan'],
                'disetujui' => $counts['disetujui'],
                'ditolak' => $counts['ditolak'],
                'ditangguhkan' => $counts['ditangguhkan'],
                'total' => array_sum($counts),
            ];
        }

        // Kepegawaian
        foreach ($data['kepegawaian'] as $type => $counts) {
            $formattedData[] = [
                'category' => 'kepegawaian', // lowercase
                'item' => str_replace(' ', '_', strtolower($type)), // lowercase and underscore
                'diajukan' => $counts['diajukan'],
                'disetujui' => $counts['disetujui'],
                'ditolak' => $counts['ditolak'],
                'ditangguhkan' => $counts['ditangguhkan'],
                'total' => array_sum($counts),
            ];
        }

        // Kompetensi
        foreach ($data['kompetensi'] as $type => $counts) {
            $formattedData[] = [
                'category' => 'kompetensi', // lowercase
                'item' => str_replace(' ', '_', strtolower($type)), // lowercase and underscore
                'diajukan' => $counts['diajukan'],
                'disetujui' => $counts['disetujui'],
                'ditolak' => $counts['ditolak'],
                'ditangguhkan' => $counts['ditangguhkan'],
                'total' => array_sum($counts),
            ];
        }

        // Penunjang
        foreach ($data['penunjang'] as $type => $counts) {
            $formattedData[] = [
                'category' => 'penunjang', // lowercase
                'item' => str_replace(' ', '_', strtolower($type)), // lowercase and underscore
                'diajukan' => $counts['diajukan'],
                'disetujui' => $counts['disetujui'],
                'ditolak' => $counts['ditolak'],
                'ditangguhkan' => $counts['ditangguhkan'],
                'total' => array_sum($counts),
            ];
        }

        // Pengembangan
        foreach ($data['pengembangan'] as $type => $counts) {
            $formattedData[] = [
                'category' => 'pengembangan', // lowercase
                'item' => str_replace(' ', '_', strtolower($type)), // lowercase and underscore
                'diajukan' => $counts['diajukan'],
                'disetujui' => $counts['disetujui'],
                'ditolak' => $counts['ditolak'],
                'ditangguhkan' => $counts['ditangguhkan'],
                'total' => array_sum($counts),
            ];
        }

        // Calculate overall totals
        $overallTotals = [
            'diajukan' => 0,
            'disetujui' => 0,
            'ditolak' => 0,
            'ditangguhkan' => 0,
            'total_keseluruhan' => 0,
        ];

        foreach ($formattedData as $row) {
            $overallTotals['diajukan'] += $row['diajukan'];
            $overallTotals['disetujui'] += $row['disetujui'];
            $overallTotals['ditolak'] += $row['ditolak'];
            $overallTotals['ditangguhkan'] += $row['ditangguhkan'];
            $overallTotals['total_keseluruhan'] += $row['total'];
        }

        // Transform overall_totals keys to lowercase with underscores
        $transformedOverallTotals = [];
        foreach ($overallTotals as $key => $value) {
            $transformedKey = str_replace(' ', '_', strtolower($key));
            $transformedOverallTotals[$transformedKey] = $value;
        }

        return response()->json([
            'status' => 'success',
            'data' => $formattedData,
            'overall_totals' => $transformedOverallTotals,
        ]);
    }

    /**
     * Mengambil daftar pegawai untuk digunakan pada dropdown filter.
     * Mengembalikan id dan nama pegawai.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPegawaiList(Request $request)
    {
        // Asumsi model SimpegPegawai ada dan memiliki kolom 'id' dan 'nama'.
        // Jika nama kolomnya berbeda, sesuaikan di sini.
        $pegawai = SimpegPegawai::select('id', 'nama')->get();

        $formattedPegawai = $pegawai->map(function ($item) {
            return [
                'id' => $item->id,
                'nama' => str_replace(' ', '_', strtolower($item->nama)), // Format nama pegawai
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedPegawai,
        ]);
    }
}
