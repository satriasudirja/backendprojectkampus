<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
// Tambahkan use model ini jika Anda punya data libur nasional
// use App\Models\HariLibur; 

class DosenRiwayatKehadiranController extends Controller
{
    /**
     * Menampilkan rekapitulasi presensi bulanan untuk dosen yang sedang login.
     */
    public function getMonthlySummary(Request $request)
    {
        // --- PERBAIKAN UTAMA ---
        // Mengambil data pegawai langsung dari Auth::user(), sesuai dengan controller Anda yang lain.
        $pegawai = Auth::user();

        // Jika karena suatu alasan data pegawai tidak ada, kirim response error.
        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengautentikasi data pegawai. Silakan login kembali.'
            ], 401);
        }

        $tahun = $request->input('tahun', date('Y'));

        // Ambil semua data absensi untuk pegawai & tahun yang dipilih
        $allRecords = SimpegAbsensiRecord::with(['cutiRecord', 'izinRecord.jenisIzin'])
            ->where('pegawai_id', $pegawai->id) // Query menggunakan $pegawai->id
            ->whereYear('tanggal_absensi', $tahun)
            ->get();
            
        // (Opsional) Ambil data hari libur nasional
        $publicHolidays = []; 
        /*
        if (class_exists(\App\Models\HariLibur::class)) {
            $publicHolidays = \App\Models\HariLibur::whereYear('tanggal', $tahun)
                                     ->pluck('tanggal')
                                     ->map(fn($date) => $date->format('Y-m-d'))
                                     ->toArray();
        }
        */

        $monthlySummary = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $recordsInMonth = $allRecords->filter(fn($record) => Carbon::parse($record->tanggal_absensi)->month == $bulan);

            $hariKerja = 0;
            $startDate = Carbon::createFromDate($tahun, $bulan, 1);
            if ($startDate->isFuture()) continue;
            
            $endDate = $startDate->copy()->endOfMonth();
            if ($endDate->isFuture()) $endDate = Carbon::now();

            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                if ($currentDate->isWeekday() && !in_array($currentDate->format('Y-m-d'), $publicHolidays)) {
                    $hariKerja++;
                }
                $currentDate->addDay();
            }

            $hadirRecords = $recordsInMonth->whereNotNull('jam_masuk');
            $hadir = $hadirRecords->count();
            $hadir_libur = $hadirRecords->filter(function ($record) use ($publicHolidays) {
                $tanggal = Carbon::parse($record->tanggal_absensi);
                return $tanggal->isWeekend() || in_array($tanggal->format('Y-m-d'), $publicHolidays);
            })->count();

            $hadir_di_hari_kerja = $hadir - $hadir_libur;
            $terlambat = $recordsInMonth->where('terlambat', true)->count();
            $pulangAwal = $recordsInMonth->where('pulang_awal', true)->count();
            $cuti = $recordsInMonth->whereNotNull('cuti_record_id')->count();
            
            $izinCollection = $recordsInMonth->whereNotNull('izin_record_id');
            $sakit = $izinCollection->filter(function ($record) {
                return $record->izinRecord && optional($record->izinRecord->jenisIzin)->nama_jenis_izin &&
                       stripos(optional($record->izinRecord->jenisIzin)->nama_jenis_izin, 'sakit') !== false;
            })->count();
            
            $izin = $izinCollection->count() - $sakit;
            $totalKehadiranWajib = $hadir_di_hari_kerja + $cuti + $izin + $sakit;
            $alpha = max(0, $hariKerja - $totalKehadiranWajib);

            $monthlySummary[] = [
                'bulan' => Carbon::create(null, $bulan)->locale('id')->isoFormat('MMMM'),
                'bulan_angka' => $bulan, 'tahun' => (int)$tahun, 'hari_kerja' => $hariKerja,
                'hadir' => $hadir, 'hadir_libur' => $hadir_libur, 'terlambat' => $terlambat,
                'pulang_awal' => $pulangAwal, 'sakit' => $sakit, 'izin' => $izin,
                'alpha' => $alpha, 'cuti' => $cuti,
            ];
        }

        $tahunOptions = SimpegAbsensiRecord::select(DB::raw('DISTINCT EXTRACT(YEAR FROM tanggal_absensi) as tahun'))
            ->whereNotNull('tanggal_absensi')->where('pegawai_id', $pegawai->id)
            ->orderBy('tahun', 'desc')->pluck('tahun');
            
        if ($tahunOptions->isEmpty()) $tahunOptions->push(date('Y'));

        return response()->json([
            'success' => true,
            'data' => $monthlySummary,
            'filters' => ['tahun_options' => $tahunOptions]
        ]);
    }
    
    /**
     * Menampilkan detail presensi harian untuk bulan tertentu.
     */
    public function getDailyDetail(Request $request)
    {
        // --- PERBAIKAN UTAMA ---
        $pegawai = Auth::user();
        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengautentikasi data pegawai. Silakan login kembali.'
            ], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|digits:4',
            'bulan' => 'required|integer|between:1,12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $perPage = $request->per_page ?? 31; 
        
        $dataHarian = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_absensi', $request->tahun)
            ->whereMonth('tanggal_absensi', $request->bulan)
            ->orderBy('tanggal_absensi', 'asc')->paginate($perPage);

        $dataHarian->getCollection()->transform(fn($item) => $this->formatPresensiData($item));
        
        return response()->json(['success' => true, 'data' => $dataHarian]);
    }
    
    /**
     * Helper untuk memformat data presensi tunggal.
     */
    private function formatPresensiData($presensi)
    {
        $status = $presensi->getAttendanceStatus();
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