<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\HolidayService; // <-- LOGIKA BARU: Menggunakan Service

class DosenRiwayatKehadiranController extends Controller
{
    protected $holidayService;

    /**
     * LOGIKA BARU: Inject HolidayService melalui constructor.
     */
    public function __construct(HolidayService $holidayService)
    {
        $this->middleware('auth:api');
        $this->holidayService = $holidayService;
    }

    /**
     * Menampilkan rekapitulasi presensi bulanan untuk dosen yang sedang login.
     */
    public function getMonthlySummary(Request $request)
    {
        $pegawai = Auth::user()->pegawai;
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Gagal mengautentikasi data pegawai.'], 401);
        }

        $tahun = $request->input('tahun', date('Y'));

        // Ambil semua data absensi untuk tahun yang dipilih untuk efisiensi
        $allRecords = SimpegAbsensiRecord::with(['cutiRecord', 'izinRecord.jenisIzin'])
            ->where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_absensi', $tahun)
            ->get();
        
        $monthlySummary = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $recordsInMonth = $allRecords->filter(fn($record) => Carbon::parse($record->tanggal_absensi)->month == $bulan);

            $startDate = Carbon::create($tahun, $bulan, 1);
            if ($startDate->isFuture()) continue;
            
            $endDate = $startDate->copy()->endOfMonth();
            if ($endDate->isFuture()) $endDate = Carbon::now();

            // LOGIKA BARU: Menghitung hari kerja menggunakan HolidayService
            $hariKerja = $this->holidayService->calculateWorkingDays($startDate, $endDate);

            $hadirRecords = $recordsInMonth->whereNotNull('jam_masuk');
            
            // Hitung kehadiran pada hari kerja dan hari libur secara terpisah
            $hadir_di_hari_kerja = $hadirRecords->filter(function ($record) {
                return !$this->holidayService->isHoliday(Carbon::parse($record->tanggal_absensi));
            })->count();
            
            $hadir_libur = $hadirRecords->count() - $hadir_di_hari_kerja;

            $cuti = $recordsInMonth->whereNotNull('cuti_record_id')->count();
            
            $izinCollection = $recordsInMonth->whereNotNull('izin_record_id');
            $sakit = $izinCollection->filter(function ($record) {
                return $record->izinRecord && stripos(optional($record->izinRecord)->jenis_izin, 'sakit') !== false;
            })->count();
            
            $izin = $izinCollection->count() - $sakit;
            
            // Hitung alpha berdasarkan hari kerja efektif
            $totalKehadiranTerhitung = $hadir_di_hari_kerja + $cuti + $izin + $sakit;
            $alpha = max(0, $hariKerja - $totalKehadiranTerhitung);

            $monthlySummary[] = [
                'bulan' => Carbon::create(null, $bulan)->locale('id')->isoFormat('MMMM'),
                'bulan_angka' => $bulan,
                'tahun' => (int)$tahun,
                'hari_kerja' => $hariKerja,
                'hadir' => $hadir_di_hari_kerja, // Hanya hadir di hari kerja
                'hadir_libur' => $hadir_libur,
                'terlambat' => 0, // Dihilangkan dari logika utama
                'pulang_awal' => 0, // Dihilangkan dari logika utama
                'sakit' => $sakit,
                'izin' => $izin,
                'alpha' => $alpha,
                'cuti' => $cuti,
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
        $pegawai = Auth::user()->pegawai;
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Gagal mengautentikasi data pegawai.'], 401);
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
     * Helper untuk memformat data presensi tunggal untuk response API.
     */
    private function formatPresensiData($presensi)
    {
        $status = $presensi->getAttendanceStatus();
        return [
            'id' => $presensi->id,
            'tanggal' => Carbon::parse($presensi->tanggal_absensi)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'jam_masuk' => optional($presensi->jam_masuk)->format('H:i:s') ?? '-',
            'jam_keluar' => optional($presensi->jam_keluar)->format('H:i:s') ?? '-',
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'keterangan' => $presensi->keterangan ?? '-',
            'durasi_kerja' => $presensi->getFormattedWorkingDuration(),
        ];
    }
}
