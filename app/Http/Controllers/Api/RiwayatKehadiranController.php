<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Services\HolidayService; // <-- LOGIKA BARU: Menggunakan Service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class RiwayatKehadiranController extends Controller
{
    protected $holidayService;

    /**
     * LOGIKA BARU: Inject HolidayService untuk digunakan di semua method.
     */
    public function __construct(HolidayService $holidayService)
    {
        $this->middleware('auth:api');
        $this->holidayService = $holidayService;
    }

    /**
     * Menampilkan riwayat kehadiran per bulan untuk pegawai yang login.
     */
    public function index(Request $request)
    {
        $pegawai = Auth::user()->pegawai;
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $request->validate(['tahun' => 'nullable|integer|digits:4']);
        $tahun = $request->input('tahun', date('Y'));

        // Panggil method helper untuk mendapatkan data rekapitulasi bulanan
        $attendanceData = $this->getMonthlyAttendanceData($pegawai->id, $tahun);
        
        return response()->json([
            'success' => true,
            'data' => $attendanceData,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai->load(['unitKerja', 'statusAktif', 'jabatanFungsional'])),
            'tahun' => (int) $tahun,
            'tahun_options' => $this->getYearOptions($pegawai->id),
            'summary' => $this->calculateYearlySummary($attendanceData),
            'table_columns' => $this->getTableColumns()
        ]);
    }

    /**
     * Menampilkan detail presensi harian untuk bulan tertentu.
     */
    public function detail(Request $request)
    {
        $pegawai = Auth::user()->pegawai;
        $request->validate([
            'tahun' => 'required|integer|digits:4',
            'bulan' => 'required|integer|between:1,12',
        ]);

        $dailyData = $this->getEmployeeDailyAttendance($pegawai->id, $request->tahun, $request->bulan);

        return response()->json([
            'success' => true,
            'data' => $dailyData,
            'periode' => $this->getMonthName($request->bulan) . ' ' . $request->tahun,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
        ]);
    }

    /**
     * Membuat data untuk dicetak.
     */
    public function print(Request $request)
    {
        $pegawai = Auth::user()->pegawai;
        $request->validate([
            'tahun' => 'required|integer|digits:4',
            'bulan' => 'required|integer|between:1,12',
        ]);

        $allMonthlyData = $this->getMonthlyAttendanceData($pegawai->id, $request->tahun);
        $monthlyData = collect($allMonthlyData)->firstWhere('bulan_number', (int) $request->bulan) ?? [];
        $dailyData = $this->getEmployeeDailyAttendance($pegawai->id, $request->tahun, $request->bulan);

        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'periode' => $this->getMonthName($request->bulan) . ' ' . $request->tahun,
            'monthly_summary' => $monthlyData,
            'daily_data' => $dailyData,
            'print_date' => now()->translatedFormat('l, d F Y, H:i:s'),
            'generated_by' => $pegawai->nama
        ]);
    }

    // --- HELPER METHODS ---

    private function getMonthlyAttendanceData($pegawaiId, $tahun)
    {
        $allRecords = SimpegAbsensiRecord::with(['cutiRecord', 'izinRecord'])
            ->where('pegawai_id', $pegawaiId)
            ->whereYear('tanggal_absensi', $tahun)->get();

        $summary = [];
        $lastMonth = ($tahun == date('Y')) ? date('n') : 12;

        for ($bulan = 1; $bulan <= $lastMonth; $bulan++) {
            $startDate = Carbon::create($tahun, $bulan, 1);
            $endDate = $startDate->copy()->endOfMonth();
            
            $hariKerja = $this->holidayService->calculateWorkingDays($startDate, $endDate);
            $recordsInMonth = $allRecords->filter(fn($r) => Carbon::parse($r->tanggal_absensi)->month == $bulan);
            
            $stats = $this->calculateStatsFromCollection($recordsInMonth, $hariKerja);

            $summary[] = [
                'bulan' => $startDate->locale('id')->isoFormat('MMMM'),
                'bulan_number' => $bulan, 'hari_kerja' => $hariKerja,
                'hadir' => $stats['hadir_di_hari_kerja'], 'hadir_libur' => $stats['hadir_libur'],
                'sakit' => $stats['sakit'], 'izin' => $stats['izin'],
                'alpa' => $stats['alpha'], 'cuti' => $stats['cuti'],
                'terlambat' => 0, 'pulang_awal' => 0,
                'aksi' => [
                    'detail_url' => url("/api/riwayat-kehadiran/detail?tahun={$tahun}&bulan={$bulan}"),
                    'print_url' => url("/api/riwayat-kehadiran/print?tahun={$tahun}&bulan={$bulan}")
                ]
            ];
        }
        return $summary;
    }
    
    private function calculateStatsFromCollection($records, $hariKerja)
    {
        $hadirRecords = $records->whereNotNull('jam_masuk');
        $hadir_di_hari_kerja = $hadirRecords->filter(fn($r) => !$this->holidayService->isHoliday(Carbon::parse($r->tanggal_absensi)))->count();
        $hadir_libur = $hadirRecords->count() - $hadir_di_hari_kerja;
        
        $cuti = $records->whereNotNull('cuti_record_id')->count();
        $izinCollection = $records->whereNotNull('izin_record_id');
        $sakit = $izinCollection->filter(fn($r) => $r->izinRecord && stripos(optional($r->izinRecord)->jenis_izin, 'sakit') !== false)->count();
        $izin = $izinCollection->count() - $sakit;

        $totalKehadiranTerhitung = $hadir_di_hari_kerja + $cuti + $izin + $sakit;
        $alpha = max(0, $hariKerja - $totalKehadiranTerhitung);
        
        return compact('hadir_di_hari_kerja', 'hadir_libur', 'sakit', 'izin', 'cuti', 'alpha');
    }
    
    private function getEmployeeDailyAttendance($pegawaiId, $tahun, $bulan)
    {
        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$startDate, $endDate])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord', 'settingKehadiran'])
            ->get()->keyBy(fn($item) => $item->tanggal_absensi->format('Y-m-d'));

        $dailyData = [];
        $no = 1;
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $record = $attendanceRecords->get($dateKey);
            $isHoliday = $this->holidayService->isHoliday($date);
            
            $status = 'Alpha';
            $keterangan = 'Tidak ada catatan kehadiran';
            
            if ($isHoliday) {
                $status = 'Libur';
                $keterangan = 'Hari libur efektif';
            }
            
            if ($record) {
                $statusInfo = $record->getAttendanceStatus();
                $status = $statusInfo['label'];
                $keterangan = $record->keterangan ?? $status;
            }

            $dailyData[] = [
                'no' => $no++,
                'hari_tanggal' => $date->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'datang' => optional(optional($record)->jam_masuk)->format('H:i') ?? '-',
                'pulang' => optional(optional($record)->jam_keluar)->format('H:i') ?? '-',
                'lokasi_datang' => optional($record)->lokasi_masuk ?? '-',
                'lokasi_pulang' => optional($record)->lokasi_keluar ?? '-',
                'jenis_presensi' => $status,
                'keterangan' => $keterangan,
            ];
        }
        return $dailyData;
    }

    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) return null;
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? '-',
            'status' => optional($pegawai->statusAktif)->nama_status_aktif ?? '-',
            'jabatan_akademik' => optional($pegawai->jabatanAkademik)->jabatan_akademik ?? '-'
        ];
    }
    
    private function getYearOptions($pegawaiId)
    {
        return SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM tanggal_absensi) as year')
            ->orderBy('year', 'desc')->get()->pluck('year');
    }

    private function calculateYearlySummary($rekapData)
    {
        $summary = ['total_hari_kerja' => 0, 'total_hadir' => 0, 'total_sakit' => 0, 'total_izin' => 0, 'total_cuti' => 0, 'total_alpa' => 0, 'total_hadir_libur' => 0];
        foreach ($rekapData as $data) {
            $summary['total_hari_kerja'] += $data['hari_kerja'];
            $summary['total_hadir'] += $data['hadir'];
            $summary['total_hadir_libur'] += $data['hadir_libur'];
            $summary['total_sakit'] += $data['sakit'];
            $summary['total_izin'] += $data['izin'];
            $summary['total_cuti'] += $data['cuti'];
            $summary['total_alpa'] += $data['alpa'];
        }
        return $summary;
    }
    
    private function getTableColumns()
    {
        return [
            ['field' => 'bulan', 'label' => 'Bulan'], ['field' => 'hari_kerja', 'label' => 'Hari Kerja'],
            ['field' => 'hadir', 'label' => 'Hadir'], ['field' => 'hadir_libur', 'label' => 'Hadir Libur'],
            ['field' => 'sakit', 'label' => 'Sakit'], ['field' => 'izin', 'label' => 'Izin'],
            ['field' => 'alpa', 'label' => 'Alpa'], ['field' => 'cuti', 'label' => 'Cuti'],
            ['field' => 'aksi', 'label' => 'Aksi']
        ];
    }
    
    private function getMonthOptions()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = ['value' => $i, 'label' => Carbon::create(null, $i)->locale('id')->isoFormat('MMMM')];
        }
        return $months;
    }

    private function getMonthName($monthNumber)
    {
        return Carbon::create(null, $monthNumber)->locale('id')->isoFormat('MMMM');
    }
}
