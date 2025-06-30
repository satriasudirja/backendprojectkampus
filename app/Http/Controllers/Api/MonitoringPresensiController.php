<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Services\HolidayService; // <-- LOGIKA BARU: Menggunakan Service
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonitoringPresensiController extends Controller
{
    protected $holidayService;

    /**
     * LOGIKA BARU: Inject HolidayService untuk digunakan di semua method.
     */
    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
        // Pastikan middleware otentikasi aktif untuk endpoint admin
        // $this->middleware('auth:api'); 
    }

    /**
     * Menampilkan daftar monitoring presensi harian untuk semua pegawai dengan filter.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $tanggal = $request->input('tanggal', date('Y-m-d'));
        $unitKerjaFilter = $request->input('unit_kerja');
        $statusPresensiFilter = $request->input('status_presensi');

        // LOGIKA BARU: Query dimulai dari Pegawai Aktif, lalu di-join dengan data absensi
        $query = SimpegPegawai::query()
            ->where('status_kerja', 'like', '%Aktif%') // Mengambil semua pegawai yang aktif
            ->leftJoin('simpeg_absensi_record', function ($join) use ($tanggal) {
                $join->on('simpeg_pegawai.id', '=', 'simpeg_absensi_record.pegawai_id')
                     ->where('simpeg_absensi_record.tanggal_absensi', '=', $tanggal);
            })
            ->with(['unitKerja', 'absensiRecords' => function ($q) use ($tanggal) {
                $q->where('tanggal_absensi', $tanggal)->with(['cutiRecord', 'izinRecord']);
            }]);

        // Filter berdasarkan Unit Kerja
        if ($unitKerjaFilter) {
            $query->where('simpeg_pegawai.unit_kerja_id', $unitKerjaFilter);
        }

        // Filter berdasarkan Status Presensi
        if ($statusPresensiFilter) {
            $this->applyStatusFilter($query, $statusPresensiFilter);
        }

        // Filter Pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('simpeg_pegawai.nama', 'like', '%' . $search . '%')
                  ->orWhere('simpeg_pegawai.nip', 'like', '%' . $search . '%');
            });
        }

        // Pilih kolom yang diperlukan dan lakukan paginasi
        $presensiData = $query->select('simpeg_pegawai.*', 'simpeg_absensi_record.id as absensi_id')
            ->orderBy('simpeg_pegawai.nama', 'asc')
            ->paginate($perPage);

        // Transformasi data untuk response
        $presensiData->getCollection()->transform(function ($pegawai) use ($tanggal) {
            return $this->formatPresensiData($pegawai, $tanggal);
        });

        return response()->json([
            'success' => true,
            'tanggal_monitoring' => $tanggal,
            'hari' => Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, DD MMMM YYYY'),
            'summary' => $this->getSummaryStatistics($tanggal, $unitKerjaFilter),
            'filter_options' => $this->getFilterOptions(),
            'data' => $presensiData,
        ]);
    }

    /**
     * Helper untuk menerapkan filter status presensi.
     */
    private function applyStatusFilter($query, $status)
    {
        switch ($status) {
            case 'hadir':
                $query->whereNotNull('simpeg_absensi_record.jam_masuk');
                break;
            case 'alpha':
                $query->whereNull('simpeg_absensi_record.id');
                break;
            case 'cuti':
                $query->whereNotNull('simpeg_absensi_record.cuti_record_id');
                break;
            case 'izin':
                $query->whereNotNull('simpeg_absensi_record.izin_record_id');
                break;
        }
    }

    /**
     * Menghitung statistik ringkasan untuk tanggal dan unit kerja tertentu.
     */
    private function getSummaryStatistics($tanggal, $unitKerjaId = null)
    {
        // Query untuk total pegawai aktif
        $totalPegawaiQuery = SimpegPegawai::where('status_kerja', 'like', '%Aktif%');
        if ($unitKerjaId) {
            $totalPegawaiQuery->where('unit_kerja_id', $unitKerjaId);
        }
        $totalPegawai = $totalPegawaiQuery->count();

        // Query untuk data absensi pada tanggal tersebut
        $baseQuery = SimpegAbsensiRecord::whereDate('tanggal_absensi', $tanggal);
        if ($unitKerjaId) {
            $baseQuery->whereHas('pegawai', fn($q) => $q->where('unit_kerja_id', $unitKerjaId));
        }

        $hadir = $baseQuery->clone()->whereNotNull('jam_masuk')->count();
        $cuti = $baseQuery->clone()->whereNotNull('cuti_record_id')->count();
        $izin = $baseQuery->clone()->whereNotNull('izin_record_id')->count();
        
        // Alpha dihitung dari selisih total pegawai dengan yang sudah memiliki record (hadir/cuti/izin)
        $alpha = $totalPegawai - ($hadir + $cuti + $izin);
        
        return [
            'total_pegawai' => $totalPegawai,
            'hadir' => $hadir,
            'alpha' => max(0, $alpha), // Pastikan tidak negatif
            'cuti' => $cuti,
            'izin' => $izin,
            'persentase_kehadiran' => $totalPegawai > 0 ? round(($hadir / $totalPegawai) * 100, 2) : 0
        ];
    }

    /**
     * Menyiapkan data filter untuk frontend.
     */
    private function getFilterOptions()
    {
        return [
            'unit_kerja' => SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit')->get()->map(function($unit) {
                return ['value' => $unit->id, 'label' => $unit->nama_unit];
            }),
            'status_presensi' => [
                ['value' => 'hadir', 'label' => 'Hadir'],
                ['value' => 'alpha', 'label' => 'Alpha'],
                ['value' => 'cuti', 'label' => 'Cuti'],
                ['value' => 'izin', 'label' => 'Izin'],
            ]
        ];
    }

    /**
     * Memformat data pegawai dan absensinya untuk ditampilkan.
     */
    private function formatPresensiData($pegawai, $tanggal)
    {
        $absensi = $pegawai->absensiRecords->first();
        $isHoliday = $this->holidayService->isHoliday(Carbon::parse($tanggal));

        $statusInfo = ['label' => 'Alpha', 'color' => 'danger'];
        if ($isHoliday) {
            $statusInfo = ['label' => 'Libur', 'color' => 'secondary'];
        } elseif ($absensi) {
            $statusInfo = $absensi->getAttendanceStatus();
        }

        $kehadiran = '-';
        if ($absensi && $absensi->jam_masuk) {
            $jamMasuk = Carbon::parse($absensi->jam_masuk)->format('H:i');
            $kehadiran = "Masuk: {$jamMasuk}";
            if ($absensi->jam_keluar) {
                $jamKeluar = Carbon::parse($absensi->jam_keluar)->format('H:i');
                $kehadiran .= ", Keluar: {$jamKeluar}";
            }
        }

        return [
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama,
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? '-',
            'jam_kerja' => 'Fleksibel', // Jam kerja tidak lagi terikat
            'kehadiran' => $kehadiran,
            'status' => $statusInfo['label'],
            'status_color' => $statusInfo['color'],
            'detail' => $absensi ? [
                'id' => $absensi->id,
                'keterangan' => $absensi->keterangan,
            ] : null
        ];
    }
}
