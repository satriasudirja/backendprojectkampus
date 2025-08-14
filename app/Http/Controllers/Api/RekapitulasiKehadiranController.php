<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegJenisIzin;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RekapitulasiKehadiranController extends Controller
{
    protected $holidayService;

    /**
     * Inject HolidayService untuk digunakan di semua method.
     */
    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
    }

    /**
     * Menampilkan rekapitulasi presensi pegawai dengan agregasi data yang efisien.
     */
    public function index(Request $request)
    {
        // 1. Validasi dan Inisialisasi Input
        $request->validate([
            'tanggal_awal' => 'nullable|date_format:Y-m-d',
            'tanggal_akhir' => 'nullable|date_format:Y-m-d|after_or_equal:tanggal_awal',
            'unit_kerja_id' => 'nullable|uuid|exists:simpeg_unit_kerja,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
        ]);

        $tanggalAwal = $request->input('tanggal_awal', Carbon::now()->startOfMonth()->toDateString());
        $tanggalAkhir = $request->input('tanggal_akhir', Carbon::now()->endOfMonth()->toDateString());
        $unitKerjaId = $request->input('unit_kerja_id');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);

        // 2. Hitung Hari Kerja menggunakan Service
        $hariKerja = $this->holidayService->calculateWorkingDays($tanggalAwal, $tanggalAkhir);

        // 3. Query Utama dengan withCount untuk efisiensi
        $rekapQuery = SimpegPegawai::query()
            ->with(['unitKerja'])
            ->where(function ($query) {
                $query->where('status_kerja', 'like', '%Aktif%')
                      ->orWhereHas('statusAktif', function ($q) {
                          $q->where('nama_status_aktif', 'like', '%aktif%');
                      });
            })
            ->withCount([
                'absensiRecords as hadir_count' => function ($query) use ($tanggalAwal, $tanggalAkhir) {
                    $query->whereBetween('tanggal_absensi', [$tanggalAwal, $tanggalAkhir])
                          ->whereNotNull('jam_masuk');
                },
                'absensiRecords as cuti_count' => function ($query) use ($tanggalAwal, $tanggalAkhir) {
                    $query->whereBetween('tanggal_absensi', [$tanggalAwal, $tanggalAkhir])
                          ->whereNotNull('cuti_record_id');
                },
                'absensiRecords as sakit_count' => function ($query) use ($tanggalAwal, $tanggalAkhir) {
                    $query->whereBetween('tanggal_absensi', [$tanggalAwal, $tanggalAkhir])
                          ->whereHas('izinRecord.jenisIzin', fn($q) => $q->where('jenis_izin', 'ilike', '%sakit%'));
                },
                'absensiRecords as izin_count' => function ($query) use ($tanggalAwal, $tanggalAkhir) {
                    $query->whereBetween('tanggal_absensi', [$tanggalAwal, $tanggalAkhir])
                          ->whereNotNull('izin_record_id')
                          ->whereDoesntHave('izinRecord.jenisIzin', fn($q) => $q->where('jenis_izin', 'ilike', '%sakit%'));
                }
            ]);

        // Filter Hierarkis untuk Unit Kerja
        if ($unitKerjaId) {
            $unitIds = $this->getAllDescendantUnitIds($unitKerjaId);
            if (!empty($unitIds)) {
                $rekapQuery->whereIn('simpeg_pegawai.unit_kerja_id', $unitIds);
            }
        }

        // Filter Pencarian
        if ($search) {
            $rekapQuery->where(function ($q) use ($search) {
                $q->where('nip', 'like', '%' . $search . '%')
                  ->orWhere('nama', 'like', '%' . $search . '%');
            });
        }
        
        $rekapQuery->orderBy('nama');
        $rekapData = $rekapQuery->paginate($perPage);

        // 4. Transformasi Data untuk Response
        // PERBAIKAN: Teruskan variabel $tanggalAwal dan $tanggalAkhir ke dalam closure
        $rekapData->getCollection()->transform(function ($pegawai) use ($hariKerja, $tanggalAwal, $tanggalAkhir) {
            $totalKehadiranTerhitung = $pegawai->hadir_count + $pegawai->cuti_count + $pegawai->izin_count + $pegawai->sakit_count;
            $alpa = max(0, $hariKerja - $totalKehadiranTerhitung);

            return [
                'nip' => $pegawai->nip,
                'nama_pegawai' => $pegawai->nama,
                'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? '-',
                'hari_kerja' => $hariKerja,
                'hadir' => (int)$pegawai->hadir_count,
                'hadir_libur' => 0,
                'terlambat' => 0,
                'pulang_awal' => 0,
                'sakit' => (int)$pegawai->sakit_count,
                'izin' => (int)$pegawai->izin_count,
                'alpa' => $alpa,
                'cuti' => (int)$pegawai->cuti_count,
                'aksi' => ['detail_url' => url("/api/admin/rekapitulasi/kehadiran/pegawai/{$pegawai->id}?tanggal_awal={$tanggalAwal}&tanggal_akhir={$tanggalAkhir}")]
            ];
        });

        // 5. Response JSON
        return response()->json([
            'success' => true,
            'message' => 'Rekapitulasi kehadiran berhasil diambil.',
            'filters' => ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'unit_kerja_id' => $unitKerjaId, 'search' => $search],
            'filter_options' => ['unit_kerja' => SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit')->get()],
            'data' => $rekapData->items(),
            'pagination' => ['total' => $rekapData->total(), 'per_page' => $rekapData->perPage(), 'current_page' => $rekapData->currentPage(), 'last_page' => $rekapData->lastPage(), 'from' => $rekapData->firstItem(), 'to' => $rekapData->lastItem()],
        ]);
    }

    /**
     * Menampilkan detail presensi untuk satu pegawai dalam rentang tanggal.
     */
    public function show(Request $request, $pegawaiId)
    {
        $request->validate([
            'tanggal_awal' => 'required|date_format:Y-m-d',
            'tanggal_akhir' => 'required|date_format:Y-m-d|after_or_equal:tanggal_awal'
        ]);
        
        $pegawai = SimpegPegawai::with('unitKerja:id,nama_unit')->find($pegawaiId);
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan.'], 404);
        }

        $absensiRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)
            ->with(['jenisKehadiran', 'cutiRecord', 'izinRecord.jenisIzin'])
            ->whereBetween('tanggal_absensi', [$request->tanggal_awal, $request->tanggal_akhir])
            ->orderBy('tanggal_absensi', 'asc')
            ->get();
            
        $formattedData = $absensiRecords->map(function ($record) {
            $status = ['label' => 'Alpha', 'color' => 'danger']; // Default
            if ($record->cuti_record_id) {
                $status = ['label' => 'Cuti', 'color' => 'primary'];
            } elseif ($record->izin_record_id && $record->izinRecord && $record->izinRecord->jenisIzin) {
                $isSakit = stripos($record->izinRecord->jenisIzin->jenis_izin, 'sakit') !== false;
                $status = $isSakit 
                    ? ['label' => 'Sakit', 'color' => 'warning'] 
                    : ['label' => 'Izin', 'color' => 'info'];
            } elseif ($record->jam_masuk) {
                 $status = ['label' => 'Hadir Lengkap', 'color' => 'success'];
                 if (!$record->jam_keluar) {
                     $status['label'] = 'Hadir (Belum Pulang)';
                     $status['color'] = 'info';
                 }
            } elseif ($record->jenisKehadiran) {
                $status = ['label' => $record->jenisKehadiran->nama_jenis, 'color' => $record->jenisKehadiran->warna ?? 'secondary'];
            }

            return [
                'hari_dan_tanggal' => Carbon::parse($record->tanggal_absensi)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'datang' => optional($record->jam_masuk)->format('H:i') ?? '-',
                'pulang' => optional($record->jam_keluar)->format('H:i') ?? '-',
                'lokasi_datang' => $record->lokasi_masuk ?? 'N/A',
                'lokasi_pulang' => $record->lokasi_keluar ?? 'N/A',
                'jenis_presensi' => $status['label'],
                'jenis_presensi_color' => $status['color'],
                'keterangan' => $record->keterangan ?? 'Belum melakukan presensi',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Detail presensi berhasil diambil.',
            'pegawai' => ['id' => $pegawai->id, 'nama' => $pegawai->nama, 'nip' => $pegawai->nip, 'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? '-'],
            'periode' => "Periode " . Carbon::parse($request->tanggal_awal)->isoFormat('D MMMM YYYY') . " s.d. " . Carbon::parse($request->tanggal_akhir)->isoFormat('D MMMM YYYY'),
            'data' => $formattedData,
        ]);
    }
    
    /**
     * Helper untuk mendapatkan semua ID unit kerja turunan (hierarki).
     */
    private function getAllDescendantUnitIds(int $parentUnitId): array
    {
        $allIds = [$parentUnitId];
        $idsToProcess = [$parentUnitId];

        while (!empty($idsToProcess)) {
            $children = SimpegUnitKerja::whereIn('parent_unit_id', $idsToProcess)->pluck('id');
            if ($children->isEmpty()) {
                break;
            }
            $allIds = array_merge($allIds, $children->toArray());
            $idsToProcess = $children->toArray();
        }
        
        return $allIds;
    }
}
