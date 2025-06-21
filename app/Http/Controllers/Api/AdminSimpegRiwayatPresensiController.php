<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatPresensiController extends Controller
{
    /**
     * Menampilkan rekapitulasi presensi bulanan untuk pegawai tertentu dalam satu tahun.
     */
    public function getMonthlySummary(Request $request, $pegawai_id)
    {
        // Eager load semua relasi yang diperlukan
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])->findOrFail($pegawai_id);

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

            $sakit = $recordsInMonth->filter(function ($record) {
                return $record->izinRecord &&
                       optional($record->izinRecord->jenisIzin)->nama_jenis_izin &&
                       stripos(optional($record->izinRecord->jenisIzin)->nama_jenis_izin, 'sakit') !== false;
            })->count();

            $alpha = $recordsInMonth->whereNull('jam_masuk')
                                    ->whereNull('cuti_record_id')
                                    ->whereNull('izin_record_id')->count();


            $monthlySummary[] = [
                'bulan' => Carbon::create(null, $bulan)->locale('id')->isoFormat('MMMM'),
                'bulan_angka' => $bulan,
                'tahun' => $tahun,
                'hari_kerja' => $hariKerja,
                'hadir' => $hadir,
                'hadir_libur' => 0, // Placeholder
                'terlambat' => $terlambat,
                'pulang_awal' => $pulangAwal,
                'sakit' => $sakit,
                'izin' => $izin - $sakit, // Izin di luar sakit
                'alpha' => $alpha,
                'cuti' => $cuti
            ];
        }

        // PERBAIKAN: Menggunakan EXTRACT(YEAR FROM ...) untuk PostgreSQL
        $tahunOptions = SimpegAbsensiRecord::select(DB::raw('DISTINCT EXTRACT(YEAR FROM tanggal_absensi) as tahun'))
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
     * Menampilkan detail presensi harian untuk bulan tertentu.
     */
    public function getDailyDetail(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        
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
     * Update/Koreksi data presensi harian oleh admin.
     */
    public function update(Request $request, $pegawai_id, $record_id)
    {
        $record = SimpegAbsensiRecord::where('pegawai_id', $pegawai_id)->findOrFail($record_id);

        $validator = Validator::make($request->all(), [
            'jam_masuk' => 'nullable|date_format:H:i:s',
            'jam_keluar' => 'nullable|date_format:H:i:s|after_or_equal:jam_masuk',
            'keterangan' => 'nullable|string|max:255',
            'status_verifikasi' => 'required|in:pending,verified,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $record->getOriginal();
        $data = $validator->validated();
        
        $tanggalAbsensi = Carbon::parse($record->tanggal_absensi)->format('Y-m-d');
        $data['jam_masuk'] = isset($data['jam_masuk']) ? $tanggalAbsensi . ' ' . $data['jam_masuk'] : null;
        $data['jam_keluar'] = isset($data['jam_keluar']) ? $tanggalAbsensi . ' ' . $data['jam_keluar'] : null;
        
        if ($data['jam_masuk']) {
            $data['cuti_record_id'] = null;
            $data['izin_record_id'] = null;
        }

        $record->update($data);
        ActivityLogger::log('update', $record, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil dikoreksi.',
            'data' => $this->formatPresensiData($record),
        ]);
    }

    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) return null;
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => trim(($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '')),
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? 'Tidak Ada',
            'status' => optional($pegawai->statusAktif)->nama_status_aktif ?? '-',
            'jab_akademik' => optional($pegawai->jabatanAkademik)->jabatan_akademik ?? '-',
            'jab_fungsional' => optional(optional($pegawai->dataJabatanFungsional->first())->jabatanFungsional)->nama_jabatan_fungsional ?? '-',
            'jab_struktural' => optional(optional(optional($pegawai->dataJabatanStruktural->first())->jabatanStruktural)->jenisJabatanStruktural)->jenis_jabatan_struktural ?? '-',
            'pendidikan' => optional(optional($pegawai->dataPendidikanFormal->first())->jenjangPendidikan)->jenjang_pendidikan ?? '-',
        ];
    }

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
