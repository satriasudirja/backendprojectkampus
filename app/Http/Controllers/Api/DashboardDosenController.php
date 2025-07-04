<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegCutiRecord;
use App\Models\SimpegIzinRecord;
use App\Models\SimpegBerita;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegEvaluasiKinerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class DashboardDosenController extends Controller
{
    /**
     * Mengambil semua data yang diperlukan untuk dashboard dosen/pegawai.
     */
    public function getDashboardData(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        
        $pegawai->load('statusAktif');

        $tanggalMulai = $request->input('tgl_mulai', Carbon::now()->subDays(29)->toDateString());
        $tanggalSelesai = $request->input('tgl_selesai', Carbon::now()->toDateString());

        return response()->json([
            'success' => true,
            'user_info' => [
                'nama' => $pegawai->nama,
                'sapaan' => 'Selamat ' . $this->getWaktuSapaan() . ', ' . $pegawai->nama,
            ],
            'status_hari_ini' => $this->getStatusHariIni($pegawai->id),
            'statistik_kehadiran' => $this->getStatistikKehadiran($pegawai->id, $tanggalMulai, $tanggalSelesai),
            'persentase_riwayat' => $this->getPersentaseRiwayat($pegawai->id),
            'berita_dan_pemberitahuan' => $this->getBerita($pegawai),
        ]);
    }

    /**
     * Endpoint terpisah untuk mengambil riwayat hadir berdasarkan rentang tanggal.
     */
    public function getRiwayatHadir(Request $request)
    {
        $pegawai = Auth::user();
        
        $validator = \Validator::make($request->all(), [
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $riwayatHadir = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereNotNull('jam_masuk')
            ->whereBetween('tanggal_absensi', [$request->tgl_mulai, $request->tgl_selesai])
            ->orderBy('tanggal_absensi', 'desc')
            ->get(['tanggal_absensi', 'jam_masuk', 'jam_keluar'])
            ->map(function ($item) {
                return [
                    'tanggal' => Carbon::parse($item->tanggal_absensi)->isoFormat('dddd, D MMMM YYYY'),
                    'jam_masuk' => $item->jam_masuk ? Carbon::parse($item->jam_masuk)->format('H:i:s') : '-',
                    'jam_keluar' => $item->jam_keluar ? Carbon::parse($item->jam_keluar)->format('H:i:s') : '-',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $riwayatHadir,
        ]);
    }

    /**
     * Mengambil data evaluasi kinerja pribadi untuk ditampilkan sebagai diagram.
     */
    public function getEvaluasiKinerjaChart(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'year' => 'nullable|digits:4|integer|min:2020',
        ]);

        $year = $validated['year'] ?? Carbon::now()->year;

        $results = SimpegEvaluasiKinerja::select(
                DB::raw('EXTRACT(MONTH FROM tanggal_penilaian) as month'),
                DB::raw('AVG(total_nilai) as average_score')
            )
            ->where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_penilaian', $year)
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        $monthlyAverages = array_fill(1, 12, 0);

        foreach ($results as $result) {
            $month = (int) $result->month;
            if (isset($monthlyAverages[$month])) {
                $monthlyAverages[$month] = round($result->average_score, 2);
            }
        }
        
        $chartDataValues = array_values($monthlyAverages);

        $monthLabels = [
            'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
        ];

        $chartData = [
            'labels' => $monthLabels,
            'data' => $chartDataValues,
        ];

        return response()->json([
            'success' => true,
            'data' => $chartData,
            'message' => 'Data grafik evaluasi kinerja pribadi untuk tahun ' . $year . ' berhasil diambil.',
        ]);
    }


    // --- Helper Methods ---

    private function getWaktuSapaan()
    {
        $hour = date('G');
        if ($hour >= 5 && $hour < 12) return "Pagi";
        if ($hour >= 12 && $hour < 15) return "Siang";
        if ($hour >= 15 && $hour < 18) return "Sore";
        return "Malam";
    }

    private function getStatusHariIni($pegawaiId)
    {
        $todayRecord = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal_absensi', Carbon::today())
            ->first();

        if ($todayRecord && $todayRecord->jam_masuk) {
            return "Anda sudah absen masuk hari ini pada jam " . Carbon::parse($todayRecord->jam_masuk)->format('H:i');
        }

        return "Hari ini Anda belum tercatat hadir.";
    }

    /**
     * [FIXED] Mengambil statistik kehadiran berdasarkan data dari simpeg_absensi_record.
     * Logika ini memastikan semua perhitungan (hadir, alfa, izin, cuti) berasal dari satu
     * sumber data yang konsisten untuk akurasi maksimal.
     */
    private function getStatistikKehadiran($pegawaiId, $mulai, $selesai)
    {
        // Mengambil semua record absensi yang relevan dalam rentang tanggal yang diberikan.
        // Ini menjadi satu-satunya sumber kebenaran (source of truth) untuk statistik.
        $absensiRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$mulai, $selesai])
            ->get();
            
        // Menghitung jumlah 'Hadir' dari record yang memiliki 'jam_masuk'.
        $hadir = $absensiRecords->whereNotNull('jam_masuk')->count();

        // Menghitung jumlah 'Cuti' dari record yang memiliki referensi 'cuti_record_id'.
        $cuti = $absensiRecords->whereNotNull('cuti_record_id')->count();

        // Menghitung jumlah 'Izin' (termasuk Sakit) dari record yang memiliki referensi 'izin_record_id'.
        $izin = $absensiRecords->whereNotNull('izin_record_id')->count();

        // Menghitung jumlah 'Alpha' dari record yang tidak memiliki jam masuk, bukan cuti, dan bukan izin.
        // Asumsi: record alpha dibuat di tabel absensi jika pegawai tidak ada keterangan.
        $alpha = $absensiRecords->whereNull('jam_masuk')
                                 ->whereNull('cuti_record_id')
                                 ->whereNull('izin_record_id')
                                 ->count();

        // Menghitung total ketidakhadiran untuk data grafik.
        $totalNonHadir = $alpha + $izin + $cuti;
        
        // Mengembalikan data dengan struktur yang sama, namun dengan perhitungan yang sudah benar.
        return [
            'total_hadir' => $hadir,
            'total_alpha' => $alpha,
            'total_izin' => $izin,
            'total_cuti' => $cuti,
            'rentang_tanggal' => Carbon::parse($mulai)->isoFormat('D MMM YYYY') . ' - ' . Carbon::parse($selesai)->isoFormat('D MMM YYYY'),
            'grafik' => [
                'labels' => ['Hadir', 'Tidak Hadir (Izin, Sakit, Cuti, Alpa)'],
                'data' => [$hadir, $totalNonHadir]
            ]
        ];
    }
    
    private function getPersentaseRiwayat($pegawaiId)
    {
        $modules = [
            \App\Models\SimpegDataPendidikanFormal::class, \App\Models\SimpegDataKeluargaPegawai::class,
            \App\Models\SimpegDataPangkat::class, \App\Models\SimpegDataJabatanAkademik::class,
            \App\Models\SimpegDataJabatanStruktural::class, \App\Models\SimpegDataHubunganKerja::class,
            \App\Models\SimpegDataDiklat::class, \App\Models\SimpegDataRiwayatPekerjaanDosen::class,
            \App\Models\SimpegDataPenghargaan::class, \App\Models\SimpegDataOrganisasi::class,
            \App\Models\SimpegDataSertifikasi::class, \App\Models\SimpegDataTes::class,
            \App\Models\SimpegDataKemampuanBahasa::class, \App\Models\SimpegDataPelanggaran::class,
            \App\Models\SimpegDataPenelitian::class, \App\Models\SimpegDataPengabdian::class,
            \App\Models\SimpegDataPublikasi::class,
        ];

        $riwayatTerisi = 0;
        foreach ($modules as $modelClass) {
            try {
                if (class_exists($modelClass) && DB::table((new $modelClass)->getTable())->where('pegawai_id', $pegawaiId)->exists()) {
                    $riwayatTerisi++;
                }
            } catch (\Exception $e) { /* Abaikan jika tabel tidak ada */ }
        }

        $totalRiwayat = count($modules);
        $persentase = ($totalRiwayat > 0) ? round(($riwayatTerisi / $totalRiwayat) * 100) : 0;
        
        return [
            'persentase' => $persentase,
            'pesan' => $persentase < 100 ? 'Segera melengkapi data riwayat Anda' : 'Data riwayat Anda sudah lengkap.',
            'is_lengkap' => $persentase === 100,
        ];
    }

    private function getBerita($pegawai)
    {
        if (!$pegawai->unit_kerja_id) {
            return collect([]);
        }

        $unitKerjaIdScope = [];
        $currentUnit = SimpegUnitKerja::find($pegawai->unit_kerja_id);
        
        while ($currentUnit) {
            $unitKerjaIdScope[] = (int) $currentUnit->id;
            if ($currentUnit->parent_unit_id) {
                $currentUnit = SimpegUnitKerja::find($currentUnit->parent_unit_id);
            } else {
                $currentUnit = null;
            }
        }

        if (empty($unitKerjaIdScope)) {
            return collect([]);
        }

        $allActiveBerita = SimpegBerita::where('tgl_posting', '<=', now())
            ->where(function ($query) {
                $query->where('tgl_expired', '>=', now())
                      ->orWhereNull('tgl_expired');
            })
            ->orderBy('prioritas', 'desc')
            ->orderBy('tgl_posting', 'desc')
            ->get();

        $filteredBerita = $allActiveBerita->filter(function ($berita) use ($unitKerjaIdScope) {
            $targetUnitIds = $berita->unit_kerja_id;

            if (!is_array($targetUnitIds)) {
                // Fallback untuk data lama yang mungkin masih string JSON
                $targetUnitIds = json_decode($targetUnitIds, true);
                if (!is_array($targetUnitIds)) return false;
            }
            
            $targetUnitIds = array_map('intval', $targetUnitIds);

            return !empty(array_intersect($unitKerjaIdScope, $targetUnitIds));
        });

        return $filteredBerita->take(5)->values()->map(function ($item) {
            return [
                'judul' => $item->judul,
                'slug' => $item->slug,
                'tanggal' => Carbon::parse($item->tgl_posting)->isoFormat('D MMMM YYYY'),
                'gambar_url' => $item->gambar_berita ? Storage::url($item->gambar_berita) : null,
            ];
        });
    }
}