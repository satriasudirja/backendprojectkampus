<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegUnitKerja;
use App\Services\HolidayService; // <-- LOGIKA BARU: Menggunakan Service
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KehadiranController extends Controller
{
    protected $holidayService;

    /**
     * LOGIKA BARU: Inject HolidayService untuk digunakan di semua method.
     */
    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
        // Jika endpoint ini memerlukan otentikasi, pastikan middleware-nya aktif.
        // Contoh: $this->middleware('auth:api'); 
    }

    /**
     * Menampilkan rekapitulasi kehadiran tahunan per bulan untuk pegawai tertentu.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), ['pegawai_id' => 'required|integer|exists:simpeg_pegawai,id']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Parameter pegawai_id wajib diisi dan valid.'], 400);
        }
        
        $pegawai = SimpegPegawai::with([
            'statusAktif', 'jabatanAkademik', 'unitKerja',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])->find($request->pegawai_id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $tahun = $request->input('tahun', date('Y'));

        $attendanceData = $this->getMonthlyAttendanceData($pegawai->id, $tahun);

        return response()->json([
            'success' => true,
            'data' => $attendanceData,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'tahun' => (int) $tahun,
            'tahun_options' => $this->getYearOptions($pegawai->id),
            'summary' => $this->calculateYearlySummary($attendanceData),
            'table_columns' => $this->getTableColumns(),
        ]);
    }

    /**
     * Menampilkan detail presensi harian untuk bulan tertentu.
     */
    public function detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'tahun' => 'required|integer|digits:4',
            'bulan' => 'required|integer|between:1,12',
        ]);
        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $pegawai = SimpegPegawai::with(['unitKerja', 'statusAktif'])->find($request->pegawai_id);
        if (!$pegawai) return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);

        $dailyData = $this->getEmployeeDailyAttendance($pegawai->id, $request->tahun, $request->bulan);

        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'periode' => $this->getMonthName($request->bulan) . ' ' . $request->tahun,
            'daily_data' => $dailyData,
            'bulan_options' => $this->getMonthOptions(),
            'tahun_options' => $this->getYearOptions($pegawai->id),
        ]);
    }

    /**
     * Membuat data untuk dicetak.
     */
    public function print(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'tahun' => 'required|integer|digits:4',
            'bulan' => 'required|integer|between:1,12',
        ]);
        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $pegawai = SimpegPegawai::with(['unitKerja', 'statusAktif'])->find($request->pegawai_id);
        if (!$pegawai) return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);

        $allMonthlyData = $this->getMonthlyAttendanceData($pegawai->id, $request->tahun);
        $monthlyData = collect($allMonthlyData)->firstWhere('bulan_number', (int) $request->bulan) ?? [];
        
        $dailyData = $this->getEmployeeDailyAttendance($pegawai->id, $request->tahun, $request->bulan);

        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'periode' => $this->getMonthName($request->bulan) . ' ' . $request->tahun,
            'monthly_summary' => $monthlyData,
            'daily_data' => $dailyData,
            'print_date' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    // --- HELPER METHODS ---

    /**
     * Helper utama untuk mengambil dan menghitung data rekapitulasi bulanan.
     */
    private function getMonthlyAttendanceData($pegawaiId, $tahun)
    {
        // 1. Ambil semua record absensi dalam satu tahun untuk efisiensi
        $allRecords = SimpegAbsensiRecord::with(['cutiRecord', 'izinRecord'])
            ->where('pegawai_id', $pegawaiId)
            ->whereYear('tanggal_absensi', $tahun)->get();

        $summary = [];
        $lastMonth = ($tahun == date('Y')) ? date('n') : 12;

        for ($bulan = 1; $bulan <= $lastMonth; $bulan++) {
            $startDate = Carbon::create($tahun, $bulan, 1);
            $endDate = $startDate->copy()->endOfMonth();
            
            // 2. Gunakan HolidayService untuk perhitungan hari kerja yang akurat
            $hariKerja = $this->holidayService->calculateWorkingDays($startDate, $endDate);
            $recordsInMonth = $allRecords->filter(fn($r) => Carbon::parse($r->tanggal_absensi)->month == $bulan);
            
            // 3. Hitung statistik berdasarkan data yang sudah diambil
            $stats = $this->calculateStatsFromCollection($recordsInMonth, $hariKerja);

            $summary[] = [
                'bulan' => $startDate->locale('id')->isoFormat('MMMM'),
                'bulan_number' => $bulan, 'hari_kerja' => $hariKerja,
                'hadir' => $stats['hadir_di_hari_kerja'], 'hadir_libur' => $stats['hadir_libur'],
                'sakit' => $stats['sakit'], 'izin' => $stats['izin'],
                'alpa' => $stats['alpha'], 'cuti' => $stats['cuti'],
                'terlambat' => 0, 'pulang_awal' => 0,
                'aksi' => [
                    'detail_url' => url("/api/kehadiran/detail?pegawai_id={$pegawaiId}&tahun={$tahun}&bulan={$bulan}"),
                    'print_url' => url("/api/kehadiran/print?pegawai_id={$pegawaiId}&tahun={$tahun}&bulan={$bulan}")
                ]
            ];
        }
        return $summary;
    }
    
    /**
     * Helper untuk menghitung statistik dari collection data, bukan dari query baru.
     */
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
    
    /**
     * Helper untuk mengambil data absensi harian seorang pegawai.
     */
    private function getEmployeeDailyAttendance($pegawaiId, $tahun, $bulan)
    {
        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->whereBetween('tanggal_absensi', [$startDate, $endDate])
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord', 'settingKehadiran'])
            ->get()->keyBy(fn($item) => $item->tanggal_absensi->format('Y-m-d'));

        $dailyData = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $record = $attendanceRecords[$dateKey] ?? null;
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
                'tanggal' => $date->format('d/m/Y'),
                'hari' => $date->locale('id')->isoFormat('dddd'),
                'status' => $status,
                'jam_masuk' => optional(optional($record)->jam_masuk)->format('H:i') ?? '-',
                'jam_keluar' => optional(optional($record)->jam_keluar)->format('H:i') ?? '-',
                'terlambat' => '-', // Tidak relevan lagi
                'pulang_awal' => '-', // Tidak relevan lagi
                'lokasi' => optional(optional($record)->settingKehadiran)->nama_gedung ?? '-',
                'keterangan' => $keterangan,
            ];
        }
        return $dailyData;
    }

    /**
     * Helper untuk memformat informasi pegawai.
     */
    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) return null;
        return [
            'id' => $pegawai->id, 'nip' => $pegawai->nip ?? '-',
            'nama' => trim(($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '')),
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? 'Tidak Ada',
            'status' => optional($pegawai->statusAktif)->nama_status_aktif ?? '-',
            'jab_akademik' => optional($pegawai->jabatanAkademik)->jabatan_akademik ?? '-',
            'jab_fungsional' => optional(optional($pegawai->dataJabatanFungsional->first())->jabatanFungsional)->nama_jabatan_fungsional ?? '-',
            'jab_struktural' => optional(optional(optional($pegawai->dataJabatanStruktural->first())->jabatanStruktural)->jenisJabatanStruktural)->jenis_jabatan_struktural ?? '-',
            'pendidikan' => optional(optional($pegawai->dataPendidikanFormal->first())->jenjangPendidikan)->jenjang_pendidikan ?? '-',
        ];
    }
    
    /**
     * Helper untuk mendapatkan opsi tahun.
     */
    private function getYearOptions($pegawaiId)
    {
        return SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM tanggal_absensi) as year')
            ->orderBy('year', 'desc')->get()->pluck('year');
    }

    /**
     * Helper untuk menjumlahkan rekap data tahunan.
     */
    private function calculateYearlySummary($rekapData)
    {
        $summary = ['hari_kerja' => 0, 'hadir' => 0, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
        foreach ($rekapData as $data) {
            foreach ($summary as $key => $val) {
                if(isset($data[$key])) $summary[$key] += $data[$key];
            }
        }
        return $summary;
    }
    
    /**
     * Helper untuk mendefinisikan kolom tabel.
     */
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
    
    /**
     * Helper untuk mendapatkan opsi bulan.
     */
    private function getMonthOptions()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = ['value' => $i, 'label' => Carbon::create(null, $i)->locale('id')->isoFormat('MMMM')];
        }
        return $months;
    }

    /**
     * Helper untuk mendapatkan nama bulan dari nomor.
     */
    private function getMonthName($monthNumber)
    {
        return Carbon::create(null, $monthNumber)->locale('id')->isoFormat('MMMM');
    }
}
