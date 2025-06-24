<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Controller untuk mengelola riwayat kehadiran dari sisi Dosen.
 * Dosen hanya dapat melihat data kehadirannya sendiri.
 */
class SimpegRiwayatKehadiranDosenController extends Controller
{
    /**
     * Menampilkan rekapitulasi presensi bulanan untuk dosen yang sedang login.
     * Mirip dengan `getMonthlySummary` di admin, tetapi untuk pengguna saat ini.
     */
    public function getMonthlySummary(Request $request)
    {
        // Mengambil data pegawai dari user yang sedang login
        $pegawai = $request->user()->pegawai; // Asumsi ada relasi 'pegawai' di model User
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan untuk user ini.'], 404);
        }

        $tahun = $request->input('tahun', date('Y'));
        
        // Ambil semua data absensi untuk pegawai & tahun yang dipilih
        $allRecords = SimpegAbsensiRecord::with(['cutiRecord', 'izinRecord.jenisIzin'])
            ->where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_absensi', $tahun)
            ->get();

        $monthlySummary = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $recordsInMonth = $allRecords->filter(function ($record) use ($bulan) {
                return Carbon::parse($record->tanggal_absensi)->month == $bulan;
            });

            // Asumsi hari kerja adalah Senin-Jumat, bisa disesuaikan
            $hariKerja = 0;
            $startDate = Carbon::create($tahun, $bulan, 1);
            $endDate = $startDate->copy()->endOfMonth();
            while ($startDate->lte($endDate)) {
                // TODO: Sesuaikan dengan hari libur nasional jika ada
                if ($startDate->isWeekday()) {
                    $hariKerja++;
                }
                $startDate->addDay();
            }

            $hadir = $recordsInMonth->whereNotNull('jam_masuk')->count();
            $terlambat = $recordsInMonth->where('terlambat', true)->count();
            $pulangAwal = $recordsInMonth->where('pulang_awal', true)->count();
            $cuti = $recordsInMonth->whereNotNull('cuti_record_id')->count();
            $izin = $recordsInMonth->whereNotNull('izin_record_id')->count();
            
            // Menghitung sakit secara spesifik dari data izin
            $sakit = $recordsInMonth->filter(function ($record) {
                return $record->izinRecord &&
                       optional($record->izinRecord->jenisIzin)->nama_jenis_izin &&
                       stripos(optional($record->izinRecord->jenisIzin)->nama_jenis_izin, 'sakit') !== false;
            })->count();

            // Alpha adalah hari kerja dimana tidak ada record absensi sama sekali
            // Perhitungan alpha yang lebih akurat mungkin memerlukan daftar hari kerja vs record yang ada
            $alpha = $recordsInMonth->whereNull('jam_masuk')
                                     ->whereNull('cuti_record_id')
                                     ->whereNull('izin_record_id')->count();

            $monthlySummary[] = [
                'bulan' => Carbon::create(null, $bulan)->locale('id')->isoFormat('MMMM'),
                'bulan_angka' => $bulan,
                'tahun' => $tahun,
                'hari_kerja' => $hariKerja,
                'hadir' => $hadir,
                'hadir_libur' => 0, // Placeholder, perlu logika tambahan jika diperlukan
                'terlambat' => $terlambat,
                'pulang_awal' => $pulangAwal,
                'sakit' => $sakit,
                'izin' => $izin - $sakit, // Hanya izin di luar sakit
                'alpha' => $alpha,
                'cuti' => $cuti
            ];
        }

        // Opsi tahun untuk filter dropdown
        $tahunOptions = SimpegAbsensiRecord::select(DB::raw('DISTINCT EXTRACT(YEAR FROM tanggal_absensi) as tahun'))
            ->where('pegawai_id', $pegawai->id)
            ->whereNotNull('tanggal_absensi')
            ->orderBy('tahun', 'desc')
            ->pluck('tahun');

        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $monthlySummary,
            'filters' => [
                'tahun_options' => $tahunOptions
            ]
        ]);
    }

    /**
     * Menampilkan detail presensi harian untuk bulan tertentu bagi dosen yang login.
     */
    public function getDailyDetail(Request $request)
    {
        $pegawai = $request->user()->pegawai;
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan.'], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|digits:4',
            'bulan' => 'required|integer|between:1,12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $perPage = $request->per_page ?? 31; 
        
        $query = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_absensi', $request->tahun)
            ->whereMonth('tanggal_absensi', $request->bulan);

        $dataHarian = $query->orderBy('tanggal_absensi', 'asc')->paginate($perPage);

        // Transformasi data untuk menyajikan format yang diinginkan
        $dataHarian->getCollection()->transform(function ($item) {
            return $this->formatPresensiData($item);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataHarian,
        ]);
    }

    /**
     * Helper untuk memformat informasi dasar pegawai.
     */
    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) return null;
        
        // Eager load relasi jika belum dimuat
        $pegawai->loadMissing([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ]);

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => trim(($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '')),
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? 'Tidak Ada',
        ];
    }

    /**
     * Helper untuk memformat data presensi harian.
     */
    private function formatPresensiData($presensi)
    {
        $status = $presensi->getAttendanceStatus(); // Memanggil method dari model
        return [
            'id' => $presensi->id,
            'tanggal' => Carbon::parse($presensi->tanggal_absensi)->isoFormat('dddd, D MMMM YYYY'),
            'jam_masuk' => $presensi->jam_masuk ? Carbon::parse($presensi->jam_masuk)->format('H:i:s') : '-',
            'jam_keluar' => $presensi->jam_keluar ? Carbon::parse($presensi->jam_keluar)->format('H:i:s') : '-',
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'keterangan' => $presensi->keterangan ?? '-',
            'durasi_kerja' => $presensi->getFormattedWorkingDuration(),
        ];
    }
}
