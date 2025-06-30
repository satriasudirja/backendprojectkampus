<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegSettingKehadiran;
use App\Models\SimpegJamKerja;
use App\Models\SimpegJenisKehadiran; // Import model Jenis Kehadiran
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbsensiController extends Controller
{
    protected $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->middleware('auth:api');
        $this->holidayService = $holidayService;
    }
    
    public function getAbsensiStatus()
    {
        $pegawai = Auth::user();
        $today = Carbon::now();
        
        $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal_absensi', $today->toDateString())
            ->first();
            
        $settingKehadiran = SimpegSettingKehadiran::first();
        $isHoliday = $this->holidayService->isHoliday($today);

        $status = [
            'sudah_absen_masuk' => (bool) ($absensiHariIni && $absensiHariIni->jam_masuk),
            'sudah_absen_keluar' => (bool) ($absensiHariIni && $absensiHariIni->jam_keluar),
            'jam_masuk_tercatat' => optional(optional($absensiHariIni)->jam_masuk)->format('H:i:s'),
            'jam_keluar_tercatat' => optional(optional($absensiHariIni)->jam_keluar)->format('H:i:s'),
            'tanggal_hari_ini' => $today->toDateString(),
            'waktu_sekarang' => $today->locale('id')->isoFormat('dddd, D MMMM YYYY, HH:mm:ss'),
            'is_holiday' => $isHoliday,
            'setting_lokasi' => $settingKehadiran && method_exists($settingKehadiran, 'getLocationInfo') ? $settingKehadiran->getLocationInfo() : null
        ];

        return response()->json(['success' => true, 'data' => $status, 'message' => 'Status absensi berhasil dimuat']);
    }

    public function absenMasuk(Request $request)
    {
        $waktuSekarang = Carbon::now();
        
        if ($this->holidayService->isHoliday($waktuSekarang)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat melakukan absensi pada hari libur.'], 422);
        }

        $pegawai = Auth::user();
        $today = $waktuSekarang->toDateString();

        if (!$waktuSekarang->between(Carbon::today()->setTime(4, 0), Carbon::today()->endOfDay())) {
            return response()->json(['success' => false, 'message' => 'Absen masuk hanya dapat dilakukan antara pukul 04:00 - 23:59.'], 422);
        }

        $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->where('tanggal_absensi', $today)->first();
        if ($absensiHariIni && $absensiHariIni->jam_masuk) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan absen masuk hari ini.'], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'rencana_kegiatan' => 'nullable|string|max:1000'
        ]);
        if ($validator->fails()) return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        
        $settingKehadiran = SimpegSettingKehadiran::first();
        $jamKerja = SimpegJamKerja::where('is_default', true)->first();
        
        $fotoPath = $request->file('foto')->store('absensi/masuk', 'public');

        $isTerlambat = false;
        $durasiTerlambat = 0;
        if ($jamKerja) {
            $jamMasukStandar = Carbon::parse($today . ' ' . $jamKerja->jam_datang);
            $toleransi = $jamKerja->toleransi_terlambat ?? 0;
            $batasWaktuMasuk = $jamMasukStandar->addMinutes($toleransi);

            if ($waktuSekarang->isAfter($batasWaktuMasuk)) {
                $isTerlambat = true;
                $durasiTerlambat = $waktuSekarang->diffInMinutes($batasWaktuMasuk);
            }
        }

        $kodeJenisKehadiran = $isTerlambat ? 'T' : 'H';
        $jenisKehadiran = SimpegJenisKehadiran::where('kode_jenis', $kodeJenisKehadiran)->first();

        if (!$jenisKehadiran) {
            return response()->json(['success' => false, 'message' => "Konfigurasi sistem untuk Jenis Kehadiran dengan kode '{$kodeJenisKehadiran}' tidak ditemukan. Harap hubungi administrator."], 500);
        }

        try {
            DB::beginTransaction();
            $absensi = SimpegAbsensiRecord::updateOrCreate(
                ['pegawai_id' => $pegawai->id, 'tanggal_absensi' => $today],
                [
                    'jam_masuk' => $waktuSekarang,
                    'latitude_masuk' => $request->latitude,
                    'longitude_masuk' => $request->longitude,
                    'lokasi_masuk' => optional($settingKehadiran)->nama_gedung ?? 'Luar Jaringan',
                    'foto_masuk' => $fotoPath,
                    'setting_kehadiran_id' => optional($settingKehadiran)->id,
                    'jam_kerja_id' => optional($jamKerja)->id,
                    'jenis_kehadiran_id' => optional($jenisKehadiran)->id,
                    'rencana_kegiatan' => $request->rencana_kegiatan,
                    'status_verifikasi' => 'pending',
                    'terlambat' => $isTerlambat,
                    'durasi_terlambat' => $durasiTerlambat,
                    'check_sum_absensi' => md5($pegawai->id . $today . $waktuSekarang->timestamp)
                ]
            );
            DB::commit();
            return response()->json(['success' => true, 'message' => "Absen masuk berhasil.", 'data' => $absensi]);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($fotoPath) && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            Log::error("Absen Masuk Gagal untuk pegawai ID {$pegawai->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan absensi: ' . $e->getMessage()], 500);
        }
    }

    public function absenKeluar(Request $request)
    {
        $pegawai = Auth::user();
        $waktuSekarang = Carbon::now();
        $today = $waktuSekarang->toDateString();

        $absensiHariIni = SimpegAbsensiRecord::with('jamKerja')->where('pegawai_id', $pegawai->id)->where('tanggal_absensi', $today)->first();
        if (!$absensiHariIni || !$absensiHariIni->jam_masuk) {
            return response()->json(['success' => false, 'message' => 'Anda belum melakukan absen masuk hari ini.'], 422);
        }
        if ($absensiHariIni->jam_keluar) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan absen keluar hari ini.'], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric', 'longitude' => 'required|numeric',
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'realisasi_kegiatan' => 'nullable|string|max:1000'
        ]);
        if ($validator->fails()) return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        
        $fotoPath = $request->file('foto')->store('absensi/keluar', 'public');
        
        $jamMasuk = Carbon::parse($absensiHariIni->jam_masuk);
        $durasi_kerja = (int) round($waktuSekarang->diffInMinutes($jamMasuk));
        $durasiKerjaFormatted = floor($durasi_kerja / 60) . ' jam ' . ($durasi_kerja % 60) . ' menit';

        $isPulangAwal = false;
        $durasiPulangAwal = 0;
        if ($absensiHariIni->jamKerja) {
            $jamPulangString = $absensiHariIni->jamKerja->jam_pulang;
            if ($jamPulangString === '00:00:00' || $jamPulangString === '00:00' || !$jamPulangString) {
                $jamPulangString = '17:00:00';
            }
            $jamPulangStandar = Carbon::parse($today . ' ' . $jamPulangString);
            if ($waktuSekarang->isBefore($jamPulangStandar)) {
                $isPulangAwal = true;
                $durasiPulangAwal = $jamPulangStandar->diffInMinutes($waktuSekarang);
            }
        }

        try {
            DB::beginTransaction();
            $absensiHariIni->update([
                'jam_keluar' => $waktuSekarang, 'latitude_keluar' => $request->latitude, 'longitude_keluar' => $request->longitude,
                'lokasi_keluar' => optional($absensiHariIni->settingKehadiran)->nama_gedung ?? 'Luar Jaringan', 'foto_keluar' => $fotoPath,
                'durasi_kerja' => $durasi_kerja, 'realisasi_kegiatan' => $request->realisasi_kegiatan,
                'pulang_awal' => $isPulangAwal, 'durasi_pulang_awal' => $durasiPulangAwal
            ]);
            DB::commit();
            return response()->json(['success' => true, 'message' => "Absen keluar berhasil. Durasi kerja: {$durasiKerjaFormatted}", 'data' => $absensiHariIni]);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($fotoPath) && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            Log::error("Absen Keluar Gagal untuk pegawai ID {$pegawai->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan absensi keluar: ' . $e->getMessage()], 500);
        }
    }

    public function getHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'nullable|integer|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Filter tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $pegawai = Auth::user();
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));

        $riwayatQuery = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_absensi', $tahun)
            ->whereMonth('tanggal_absensi', $bulan)
            ->orderBy('tanggal_absensi', 'desc');

        $riwayatPaginator = $riwayatQuery->paginate(10);

        $transformedData = $riwayatPaginator->getCollection()->transform(function ($item) {
            $status = $item->getAttendanceStatus();
            return [
                'id' => $item->id,
                'tanggal' => Carbon::parse($item->tanggal_absensi)->toDateString(), // Format YYYY-MM-DD
                'tanggal_formatted' => Carbon::parse($item->tanggal_absensi)->locale('id')->isoFormat('dddd, D MMMM YYYY'), // Format untuk display
                'jam_masuk' => $item->jam_masuk ? Carbon::parse($item->jam_masuk)->format('H:i') : '-',
                'jam_keluar' => $item->jam_keluar ? Carbon::parse($item->jam_keluar)->format('H:i') : '-',
                'status_label' => $status['label'],
                'status_color' => $status['color'],
                'durasi_kerja' => $item->getFormattedWorkingDuration(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Riwayat absensi berhasil dimuat.',
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $riwayatPaginator->currentPage(),
                'per_page' => $riwayatPaginator->perPage(),
                'total' => $riwayatPaginator->total(),
                'last_page' => $riwayatPaginator->lastPage(),
            ]
        ]);
    }

    public function getDetail($id)
    {
        $pegawai = Auth::user();
        $absensiRecord = SimpegAbsensiRecord::find($id);

        if (!$absensiRecord) {
            return response()->json(['success' => false, 'message' => 'Data absensi tidak ditemukan.'], 404);
        }

        if ($absensiRecord->pegawai_id !== $pegawai->id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk melihat data ini.'], 403);
        }
        
        $detailData = $absensiRecord->getFullAttendanceInfo();

        return response()->json([
            'success' => true,
            'message' => 'Detail absensi berhasil dimuat.',
            'data' => $detailData
        ]);
    }

    public function requestCorrection(Request $request, $id)
    {
        $pegawai = Auth::user();
        $absensi = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->find($id);

        if (!$absensi) {
            return response()->json(['success' => false, 'message' => 'Data absensi tidak ditemukan'], 404);
        }

        if (method_exists($absensi, 'canBeCorrected') && !$absensi->canBeCorrected()) {
            return response()->json(['success' => false, 'message' => 'Absensi ini tidak dapat dikoreksi (sudah diverifikasi atau melewati batas waktu)'], 422);
        }

        $validator = Validator::make($request->all(), [
            'alasan_koreksi' => 'required|string|max:1000',
            'dokumen_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }
        
        $absensi->update([
            'status_verifikasi' => 'correction_requested',
            'keterangan' => ($absensi->keterangan ? $absensi->keterangan . ' | ' : '') . 'KOREKSI: ' . $request->alasan_koreksi
        ]);

        return response()->json(['success' => true, 'message' => 'Permintaan koreksi absensi berhasil dikirim']);
    }

    public function verifyAbsensi(Request $request, $id)
    {
        $verifikator = Auth::user();
        $absensi = SimpegAbsensiRecord::with('pegawai')->find($id);

        if (!$absensi) {
            return response()->json(['success' => false, 'message' => 'Data absensi tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected',
            'catatan' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        if (method_exists($absensi, 'updateVerificationStatus')) {
            $absensi->updateVerificationStatus($request->status, $verifikator->id, $request->catatan);
        } else {
            $absensi->update([
                'status_verifikasi' => $request->status,
                'verifikasi_oleh' => $verifikator->id,
                'verifikasi_at' => now(),
                'keterangan' => ($absensi->keterangan ? $absensi->keterangan . ' | ' : '') . 'VERIFIKASI: ' . $request->catatan
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status absensi berhasil diperbarui.',
            'data' => $absensi
        ]);
    }

    public function getAbsensiForVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,correction_requested',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Filter tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $query = SimpegAbsensiRecord::with('pegawai:id,nama,nip')
            ->whereIn('status_verifikasi', [$request->input('status', 'pending'), 'correction_requested']);

        if ($request->has('tanggal_mulai') && $request->has('tanggal_selesai')) {
            $query->whereBetween('tanggal_absensi', [$request->tanggal_mulai, $request->tanggal_selesai]);
        }

        $absensiList = $query->orderBy('tanggal_absensi', 'desc')->paginate(15);

        return response()->json(['success' => true, 'data' => $absensiList]);
    }

    public function getDashboardStats(Request $request)
    {
        $pegawai = Auth::user();
        $bulan = $request->input('bulan', date('n'));
        $tahun = $request->input('tahun', date('Y'));

        $query = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_absensi', $tahun)
            ->whereMonth('tanggal_absensi', $bulan);

        $stats = [
            'total_hadir' => (clone $query)->whereNotNull('jam_masuk')->count(),
            'total_terlambat' => (clone $query)->where('terlambat', true)->count(),
            'total_pulang_awal' => (clone $query)->where('pulang_awal', true)->count(),
            'total_cuti' => (clone $query)->whereNotNull('cuti_record_id')->count(),
            'total_izin' => (clone $query)->whereNotNull('izin_record_id')->count(),
        ];

        $hariKerja = 0;
        $tanggal = Carbon::create($tahun, $bulan, 1);
        $akhirBulan = $tanggal->copy()->endOfMonth();
        while($tanggal <= $akhirBulan) {
            if ($tanggal->isWeekday() && !$this->holidayService->isHoliday($tanggal)) {
                $hariKerja++;
            }
            $tanggal->addDay();
        }

        $stats['total_alpha'] = max(0, $hariKerja - ($stats['total_hadir'] + $stats['total_cuti'] + $stats['total_izin']));
        
        return response()->json(['success' => true, 'data' => $stats]);
    }
}
