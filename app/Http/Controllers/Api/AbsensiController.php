<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegSettingKehadiran;
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
            ->where('tanggal_absensi', $today->toDateString())
            ->first();
            
        $settingKehadiran = SimpegSettingKehadiran::first();

        // Cek apakah hari ini libur
        $isHoliday = $this->holidayService->isHoliday($today);

        $status = [
            'sudah_absen_masuk' => (bool) ($absensiHariIni && $absensiHariIni->jam_masuk),
            'sudah_absen_keluar' => (bool) ($absensiHariIni && $absensiHariIni->jam_keluar),
            'jam_masuk_tercatat' => optional(optional($absensiHariIni)->jam_masuk)->format('H:i:s'),
            'jam_keluar_tercatat' => optional(optional($absensiHariIni)->jam_keluar)->format('H:i:s'),
            'tanggal_hari_ini' => $today->toDateString(),
            'waktu_sekarang' => $today->locale('id')->isoFormat('dddd, D MMMM YYYY, HH:mm:ss'),
            'is_holiday' => $isHoliday, // Tambahkan status hari libur
            'setting_lokasi' => $settingKehadiran && method_exists($settingKehadiran, 'getLocationInfo') ? $settingKehadiran->getLocationInfo() : null
        ];

        return response()->json(['success' => true, 'data' => $status, 'message' => 'Status absensi berhasil dimuat']);
    }

    public function absenMasuk(Request $request)
    {
        $waktuSekarang = Carbon::now();
        
        // LOGIKA BARU: Validasi Hari Libur
        if ($this->holidayService->isHoliday($waktuSekarang)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat melakukan absensi pada hari libur.'], 422);
        }

        $pegawai = Auth::user();
        $today = $waktuSekarang->toDateString();

        // Validasi jam masuk antara 04:00 - 23:59
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
        
        $fotoPath = $request->file('foto')->store('absensi/masuk', 'public');

        try {
            DB::beginTransaction();
            $absensi = SimpegAbsensiRecord::updateOrCreate(
                ['pegawai_id' => $pegawai->id, 'tanggal_absensi' => $today],
                [
                    'jam_masuk' => $waktuSekarang, 'latitude_masuk' => $request->latitude, 'longitude_masuk' => $request->longitude,
                    'lokasi_masuk' => optional($settingKehadiran)->nama_gedung ?? 'Luar Jaringan', 'foto_masuk' => $fotoPath,
                    'setting_kehadiran_id' => optional($settingKehadiran)->id, 'rencana_kegiatan' => $request->rencana_kegiatan,
                    'status_verifikasi' => 'pending', 'terlambat' => false, 'durasi_terlambat' => 0
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
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan absensi, terjadi kesalahan pada server.'], 500);
        }
    }

    public function absenKeluar(Request $request)
    {
        $pegawai = Auth::user();
        $waktuSekarang = Carbon::now();
        $today = $waktuSekarang->toDateString();

        $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->where('tanggal_absensi', $today)->first();
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

        try {
            DB::beginTransaction();
            $absensiHariIni->update([
                'jam_keluar' => $waktuSekarang, 'latitude_keluar' => $request->latitude, 'longitude_keluar' => $request->longitude,
                'lokasi_keluar' => optional($absensiHariIni->settingKehadiran)->nama_gedung ?? 'Luar Jaringan', 'foto_keluar' => $fotoPath,
                'durasi_kerja' => $durasi_kerja, 'realisasi_kegiatan' => $request->realisasi_kegiatan,
                'pulang_awal' => false, 'durasi_pulang_awal' => 0
            ]);
            DB::commit();
            return response()->json(['success' => true, 'message' => "Absen keluar berhasil. Durasi kerja: {$durasiKerjaFormatted}", 'data' => $absensiHariIni]);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($fotoPath) && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            Log::error("Absen Keluar Gagal untuk pegawai ID {$pegawai->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan absensi keluar, terjadi kesalahan pada server.'], 500);
        }
    }
}
