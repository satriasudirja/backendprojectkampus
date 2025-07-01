<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonitoringPresensiController extends Controller
{
    protected $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
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

        $query = SimpegPegawai::query()
            ->leftJoin('simpeg_unit_kerja', 'simpeg_pegawai.unit_kerja_id', '=', 'simpeg_unit_kerja.id')
            ->leftJoin('simpeg_absensi_record', function ($join) use ($tanggal) {
                $join->on('simpeg_pegawai.id', '=', 'simpeg_absensi_record.pegawai_id')
                     ->whereDate('simpeg_absensi_record.tanggal_absensi', $tanggal);
            })
            ->leftJoin('simpeg_jenis_kehadiran', 'simpeg_absensi_record.jenis_kehadiran_id', '=', 'simpeg_jenis_kehadiran.id')
            ->where('simpeg_pegawai.status_kerja', 'like', '%Aktif%');

        if ($unitKerjaFilter) {
            $query->where('simpeg_pegawai.unit_kerja_id', $unitKerjaFilter);
        }

        if ($statusPresensiFilter) {
            $this->applyStatusFilter($query, $statusPresensiFilter);
        }

        // =================================================================
        // PERBAIKAN: Logika pencarian diperluas dan diperbaiki
        // =================================================================
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('simpeg_pegawai.nama', 'like', '%' . $search . '%')
                  ->orWhere('simpeg_pegawai.nip', 'like', '%' . $search . '%')
                  ->orWhere('simpeg_unit_kerja.nama_unit', 'like', '%' . $search . '%')
                  ->orWhere('simpeg_jenis_kehadiran.nama_jenis', 'like', '%' . $search . '%');

                // Logika khusus untuk mencari status "Alpha"
                if (stripos($search, 'alpha') !== false) {
                    $q->orWhereNull('simpeg_absensi_record.id');
                }
            });
        }

        $presensiPaginator = $query->select(
                'simpeg_pegawai.id as pegawai_id',
                'simpeg_pegawai.nip',
                'simpeg_pegawai.nama as nama_pegawai',
                'simpeg_unit_kerja.nama_unit',
                'simpeg_absensi_record.id as absensi_id',
                'simpeg_absensi_record.jam_masuk',
                'simpeg_absensi_record.jam_keluar',
                'simpeg_absensi_record.keterangan',
                'simpeg_absensi_record.cuti_record_id',
                'simpeg_absensi_record.izin_record_id',
                'simpeg_jenis_kehadiran.nama_jenis as status_kehadiran'
            )
            // =================================================================
            // PERBAIKAN: Urutkan berdasarkan absen keluar, lalu absen masuk terbaru
            // =================================================================
            ->orderBy('simpeg_absensi_record.jam_keluar', 'desc')
            ->orderBy('simpeg_absensi_record.jam_masuk', 'desc')
            ->orderBy('simpeg_pegawai.nama', 'asc') // Urutan sekunder berdasarkan nama
            ->paginate($perPage);

        $transformedData = $presensiPaginator->getCollection()->transform(function ($item) use ($tanggal) {
            return $this->formatJoinedPresensiData($item, $tanggal);
        });

        return response()->json([
            'success' => true,
            'tanggal_monitoring' => $tanggal,
            'hari' => Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, DD MMMM YYYY'),
            'summary' => $this->getSummaryStatistics($tanggal, $unitKerjaFilter),
            'filter_options' => $this->getFilterOptions(),
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $presensiPaginator->currentPage(),
                'per_page' => $presensiPaginator->perPage(),
                'total' => $presensiPaginator->total(),
                'last_page' => $presensiPaginator->lastPage(),
            ],
        ]);
    }

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

    private function getSummaryStatistics($tanggal, $unitKerjaId = null)
    {
        $totalPegawaiQuery = SimpegPegawai::where('status_kerja', 'like', '%Aktif%');
        if ($unitKerjaId) {
            $totalPegawaiQuery->where('unit_kerja_id', $unitKerjaId);
        }
        $totalPegawai = $totalPegawaiQuery->count();

        $baseQuery = SimpegAbsensiRecord::whereDate('tanggal_absensi', $tanggal);
        if ($unitKerjaId) {
            $baseQuery->whereHas('pegawai', fn($q) => $q->where('unit_kerja_id', $unitKerjaId));
        }

        $hadir = $baseQuery->clone()->whereNotNull('jam_masuk')->count();
        $cuti = $baseQuery->clone()->whereNotNull('cuti_record_id')->count();
        $izin = $baseQuery->clone()->whereNotNull('izin_record_id')->count();
        
        $alpha = $totalPegawai - ($hadir + $cuti + $izin);
        
        return [
            'total_pegawai' => $totalPegawai,
            'hadir' => $hadir,
            'alpha' => max(0, $alpha),
            'cuti' => $cuti,
            'izin' => $izin,
            'persentase_kehadiran' => $totalPegawai > 0 ? round(($hadir / $totalPegawai) * 100, 2) : 0
        ];
    }

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
    
    private function formatJoinedPresensiData($item, $tanggal)
    {
        $isHoliday = $this->holidayService->isHoliday(Carbon::parse($tanggal));
        
        $status = 'Alpha';
        $kehadiran = '-';

        if ($isHoliday) {
            $status = 'Libur';
            $kehadiran = 'Libur';
        } elseif ($item->absensi_id) {
            if ($item->cuti_record_id) {
                $status = 'Cuti';
                $kehadiran = 'Cuti';
            } elseif ($item->izin_record_id) {
                $status = 'Izin';
                $kehadiran = 'Izin';
            } elseif ($item->status_kehadiran) {
                $status = $item->status_kehadiran;
                if ($item->jam_masuk && in_array($status, ['Hadir', 'Terlambat'])) {
                    $jamMasuk = Carbon::parse($item->jam_masuk)->format('H:i');
                    $kehadiran = "Masuk: {$jamMasuk}";
                    if ($item->jam_keluar) {
                        $jamKeluar = Carbon::parse($item->jam_keluar)->format('H:i');
                        $kehadiran .= ", Keluar: {$jamKeluar}";
                    }
                } else {
                    $kehadiran = $status;
                }
            } elseif ($item->jam_masuk) {
                $status = 'Hadir';
                $jamMasuk = Carbon::parse($item->jam_masuk)->format('H:i');
                $kehadiran = "Masuk: {$jamMasuk}";
                if ($item->jam_keluar) {
                    $jamKeluar = Carbon::parse($item->jam_keluar)->format('H:i');
                    $kehadiran .= ", Keluar: {$jamKeluar}";
                }
            }
        }

        return [
            'id' => $item->pegawai_id,
            'nip' => $item->nip,
            'nama_pegawai' => $item->nama_pegawai,
            'unit_kerja' => $item->nama_unit ?? '-',
            'jam_kerja' => 'Fleksibel',
            'kehadiran' => $kehadiran,
            'status' => $status,
        ];
    }
}
