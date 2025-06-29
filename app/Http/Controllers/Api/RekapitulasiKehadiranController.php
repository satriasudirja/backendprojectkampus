<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegAbsensiRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class RekapitulasiKehadiranController extends Controller
{
    /**
     * Menampilkan rekapitulasi presensi bulanan pegawai dengan agregasi data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // 1. Validasi dan Inisialisasi Input
        $request->validate([
            'tanggal_awal' => 'nullable|date_format:Y-m-d',
            'tanggal_akhir' => 'nullable|date_format:Y-m-d|after_or_equal:tanggal_awal',
            'unit_kerja_id' => 'nullable|integer|exists:simpeg_unit_kerja,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
        ]);

        $tanggalAwal = $request->input('tanggal_awal', Carbon::now()->startOfMonth()->toDateString());
        $tanggalAkhir = $request->input('tanggal_akhir', Carbon::now()->toDateString());
        $unitKerjaId = $request->input('unit_kerja_id');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);
        
        $GLOBALS['tanggalAwal'] = $tanggalAwal;
        $GLOBALS['tanggalAkhir'] = $tanggalAkhir;

        // 2. Hitung Hari Kerja
        $hariKerja = $this->calculateWorkingDays($tanggalAwal, $tanggalAkhir);

        // 3. Query Utama untuk Rekapitulasi per Pegawai
        $rekapQuery = SimpegPegawai::query()
            // =================================================================
            // PERUBAHAN: Menggunakan leftJoin untuk mengambil nama_unit
            // =================================================================
            ->leftJoin('simpeg_unit_kerja', 'simpeg_pegawai.unit_kerja_id', '=', 'simpeg_unit_kerja.id')
            ->select([
                'simpeg_pegawai.id',
                'simpeg_pegawai.nip',
                'simpeg_pegawai.nama',
                'simpeg_pegawai.unit_kerja_id',
                'simpeg_unit_kerja.nama_unit' // Ambil nama_unit langsung dari join
            ])
            ->where(function ($query) {
                $query->where('simpeg_pegawai.status_kerja', 'like', '%Aktif%')
                      ->orWhereHas('statusAktif', function ($q) {
                          $q->where('nama_status_aktif', 'like', '%aktif%');
                      });
            });

        // Subquery untuk Agregasi data absensi
        $rekapQuery->addSelect([
            'hadir' => $this->createStatusSubquery('hadir', $tanggalAwal, $tanggalAkhir),
            'terlambat' => $this->createStatusSubquery('terlambat', $tanggalAwal, $tanggalAkhir),
            'pulang_awal' => $this->createStatusSubquery('pulang_awal', $tanggalAwal, $tanggalAkhir),
            'cuti' => $this->createStatusSubquery('cuti', $tanggalAwal, $tanggalAkhir),
            'izin' => $this->createStatusSubquery('izin', $tanggalAwal, $tanggalAkhir),
            'sakit' => DB::raw('0'), // Placeholder
        ]);

        // Filter Hierarkis untuk Unit Kerja
        if ($unitKerjaId) {
            // =================================================================
            // PERUBAHAN: Mengambil ID integer, bukan kode string
            // =================================================================
            $unitIds = $this->getAllDescendantUnitIds($unitKerjaId);
            if (!empty($unitIds)) {
                $rekapQuery->whereIn('simpeg_pegawai.unit_kerja_id', $unitIds);
            }
        }

        // Filter Pencarian
        if ($search) {
            $rekapQuery->where(function ($q) use ($search) {
                $q->where('simpeg_pegawai.nip', 'like', '%' . $search . '%')
                  ->orWhere('simpeg_pegawai.nama', 'like', '%' . $search . '%');
            });
        }
        
        $rekapQuery->orderBy('simpeg_pegawai.nama');
        $rekapData = $rekapQuery->paginate($perPage);

        // 4. Transformasi Data
        $transformedData = $rekapData->getCollection()->map(function ($pegawai) use ($hariKerja) {
            $totalKehadiran = $pegawai->hadir + $pegawai->cuti + $pegawai->izin + $pegawai->sakit;
            $alpa = max(0, $hariKerja - $totalKehadiran);

            return [
                'nip' => $pegawai->nip,
                'nama_pegawai' => $pegawai->nama,
                'unit_kerja' => $pegawai->nama_unit ?? '-', // Gunakan nama_unit dari hasil join
                'hari_kerja' => $hariKerja,
                'hadir' => (int)$pegawai->hadir,
                'hadir_libur' => 0,
                'terlambat' => (int)$pegawai->terlambat,
                'pulang_awal' => (int)$pegawai->pulang_awal,
                'sakit' => (int)$pegawai->sakit,
                'izin' => (int)$pegawai->izin,
                'alpa' => $alpa,
                'cuti' => (int)$pegawai->cuti,
                'aksi' => ['detail_url' => url("/api/admin/rekapitulasi/kehadiran/pegawai/{$pegawai->id}?tanggal_awal={$GLOBALS['tanggalAwal']}&tanggal_akhir={$GLOBALS['tanggalAkhir']}")]
            ];
        });
        
        unset($GLOBALS['tanggalAwal'], $GLOBALS['tanggalAkhir']);

        // 5. Response JSON
        return response()->json([
            'success' => true,
            'message' => 'Rekapitulasi kehadiran berhasil diambil.',
            'filters' => ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'unit_kerja_id' => $unitKerjaId, 'search' => $search],
            'filter_options' => ['unit_kerja' => SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit')->get()],
            'data' => $transformedData,
            'pagination' => ['total' => $rekapData->total(), 'per_page' => $rekapData->perPage(), 'current_page' => $rekapData->currentPage(), 'last_page' => $rekapData->lastPage(), 'from' => $rekapData->firstItem(), 'to' => $rekapData->lastItem()],
        ]);
    }

    /**
     * Menampilkan detail presensi untuk satu pegawai.
     */
    public function show(Request $request, $pegawaiId)
    {
        $request->validate(['tanggal_awal' => 'required|date_format:Y-m-d', 'tanggal_akhir' => 'required|date_format:Y-m-d|after_or_equal:tanggal_awal']);
        $tanggalAwal = $request->input('tanggal_awal');
        $tanggalAkhir = $request->input('tanggal_akhir');
        $pegawai = SimpegPegawai::with('unitKerja:id,nama_unit')->find($pegawaiId);
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan.'], 404);
        }
        $absensiRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawaiId)->whereBetween('tanggal_absensi', [$tanggalAwal, $tanggalAkhir])->orderBy('tanggal_absensi', 'asc')->get();
        $formattedData = $absensiRecords->map(function ($record) {
            $status = $record->getAttendanceStatus();
            return [
                'hari_dan_tanggal' => Carbon::parse($record->tanggal_absensi)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'datang' => $record->jam_masuk ? Carbon::parse($record->jam_masuk)->format('H:i') : '-',
                'pulang' => $record->jam_keluar ? Carbon::parse($record->jam_keluar)->format('H:i') : '-',
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
            'pegawai' => ['id' => $pegawai->id, 'nama' => $pegawai->nama, 'nip' => $pegawai->nip, 'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-'],
            'periode' => "Periode " . Carbon::parse($tanggalAwal)->isoFormat('D MMMM YYYY') . " s.d. " . Carbon::parse($tanggalAkhir)->isoFormat('D MMMM YYYY'),
            'data' => $formattedData,
        ]);
    }
    
    private function createStatusSubquery(string $status, string $tanggalAwal, string $tanggalAkhir)
    {
        $query = DB::table('simpeg_absensi_record')->select(DB::raw('count(*)'))->whereColumn('simpeg_absensi_record.pegawai_id', 'simpeg_pegawai.id')->whereBetween('tanggal_absensi', [$tanggalAwal, $tanggalAkhir]);
        switch ($status) {
            case 'hadir': $query->whereNotNull('jam_masuk'); break;
            case 'terlambat': $query->where('terlambat', true); break;
            case 'pulang_awal': $query->where('pulang_awal', true); break;
            case 'cuti': $query->whereNotNull('cuti_record_id'); break;
            case 'izin': $query->whereNotNull('izin_record_id'); break;
        }
        return $query;
    }

    private function calculateWorkingDays(string $startDate, string $endDate): int
    {
        try {
            $period = CarbonPeriod::create($startDate, $endDate);
            $workingDays = 0;
            foreach ($period as $date) {
                if ($date->isWeekday()) { $workingDays++; }
            }
            return $workingDays;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * =================================================================
     * PERUBAHAN: Fungsi ini sekarang mengambil ID (integer) bukan kode (string)
     * =================================================================
     */
    private function getAllDescendantUnitIds(int $parentUnitId): array
    {
        $allIds = [$parentUnitId];
        $idsToProcess = [$parentUnitId];

        // Menggunakan loop untuk mencari semua turunan secara rekursif
        while (!empty($idsToProcess)) {
            // Mengambil semua anak dari unit yang sedang diproses
            // Asumsi relasi parent-child menggunakan 'parent_unit_id' yang merujuk ke 'id'
            $children = SimpegUnitKerja::whereIn('parent_unit_id', $idsToProcess)->get(['id']);
            
            $idsToProcess = [];

            foreach ($children as $child) {
                if (!in_array($child->id, $allIds)) {
                    $allIds[] = $child->id;
                    $idsToProcess[] = $child->id;
                }
            }
        }
        
        return $allIds;
    }
}
