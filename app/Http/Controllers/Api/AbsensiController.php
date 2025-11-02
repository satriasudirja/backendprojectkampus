<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegSettingKehadiran;
use App\Models\SimpegJamKerja;
use App\Models\SimpegJenisKehadiran;
use App\Services\HolidayService;
use App\Services\QrPinBundleGenerator;
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
        $pegawai = Auth::user()->pegawai;
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
            'metode_absensi' => optional($absensiHariIni)->metode_absensi,
            'setting_lokasi' => $settingKehadiran && method_exists($settingKehadiran, 'getLocationInfo') ? $settingKehadiran->getLocationInfo() : null
        ];

        return response()->json(['success' => true, 'data' => $status, 'message' => 'Status absensi berhasil dimuat']);
    }

    /**
     * ABSEN MASUK - QR CODE SEBAGAI METODE UTAMA
     * Foto menjadi opsional
     */
    public function absenMasuk(Request $request)
    {
        $waktuSekarang = Carbon::now();
        
        if ($this->holidayService->isHoliday($waktuSekarang)) {
            return response()->json([
                'success' => false, 
                'message' => 'Tidak dapat melakukan absensi pada hari libur.'
            ], 422);
        }

        $pegawai = Auth::user()->pegawai;
        $today = $waktuSekarang->toDateString();

        if (!$waktuSekarang->between(Carbon::today()->setTime(4, 0), Carbon::today()->endOfDay())) {
            return response()->json([
                'success' => false, 
                'message' => 'Absen masuk hanya dapat dilakukan antara pukul 04:00 - 23:59.'
            ], 422);
        }

        $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->where('tanggal_absensi', $today)
            ->first();
            
        if ($absensiHariIni && $absensiHariIni->jam_masuk) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda sudah melakukan absen masuk hari ini.'
            ], 422);
        }

        // Determine method
        $metodeAbsensi = 'manual';
        $pinCode = null;
        $usingQrScan = false;
        $usingManualPin = false;

        // VALIDATION RULES
        $validationRules = [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'rencana_kegiatan' => 'nullable|string|max:1000'
        ];

        // Check if QR was scanned (contains qr_data field)
        if ($request->has('qr_data') && !empty($request->qr_data)) {
            $validationRules['qr_data'] = 'required|string';
            $validationRules['foto'] = 'nullable|image|mimes:jpeg,png,jpg|max:5120';
            $usingQrScan = true;
        }
        // Check if PIN was manually entered
        elseif ($request->has('pin_code') && !empty($request->pin_code)) {
            $validationRules['pin_code'] = 'required|string|min:6|max:8';
            $validationRules['foto'] = 'nullable|image|mimes:jpeg,png,jpg|max:5120';
            $usingManualPin = true;
        }
        // Manual/Photo mode
        else {
            $validationRules['foto'] = 'required|image|mimes:jpeg,png,jpg|max:5120';
        }
        
        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validasi gagal.', 
                'errors' => $validator->errors()
            ], 422);
        }

        // PROCESS QR SCAN OR PIN
        $settingKehadiran = null;
        
        try {
            if ($usingQrScan) {
                // Decode PIN from QR scan
                $decoded = QrPinBundleGenerator::decodePinFromQr($request->qr_data);
                
                if (!$decoded['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'QR Code tidak valid. Silakan scan ulang atau masukkan PIN manual.'
                    ], 400);
                }
                
                $pinCode = $decoded['pin'];
                $metodeAbsensi = 'qr_scan';
                
                \Log::info('QR scanned, extracted PIN: ' . $pinCode);
                
            } elseif ($usingManualPin) {
                // Use manually entered PIN
                $pinCode = $request->pin_code;
                $metodeAbsensi = 'pin_manual';
                
                \Log::info('Manual PIN entered: ' . $pinCode);
            } else {
                // Manual photo mode
                $metodeAbsensi = 'foto';
            }
            
            // Validate PIN if provided (from QR or manual)
            if ($pinCode) {
                $settingKehadiran = SimpegSettingKehadiran::where('qr_pin_code', $pinCode)
                    ->where('qr_pin_enabled', true)
                    ->first();
                
                if (!$settingKehadiran) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PIN tidak valid atau sudah tidak aktif. PIN: ' . $pinCode
                    ], 404);
                }

                // Check PIN expiration
                if ($settingKehadiran->isPinExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PIN sudah kadaluarsa. Silakan hubungi admin untuk PIN baru.'
                    ], 400);
                }
                
                \Log::info('PIN validated successfully for setting: ' . $settingKehadiran->nama_gedung);
            } else {
                // No PIN, use default setting
                $settingKehadiran = SimpegSettingKehadiran::first();
            }
            
        } catch (\Exception $e) {
            Log::error('QR/PIN processing error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses. Silakan coba lagi.'
            ], 500);
        }

        // VALIDATE LOCATION
        if ($settingKehadiran && $settingKehadiran->wajib_presensi_dilokasi) {
            $isWithinRadius = $settingKehadiran->isWithinRadius($request->latitude, $request->longitude);
            
            if (!$isWithinRadius) {
                $distance = round($settingKehadiran->calculateDistance($request->latitude, $request->longitude), 2);
                return response()->json([
                    'success' => false, 
                    'message' => "Anda berada di luar radius presensi. Jarak: {$distance}m, Maksimal: {$settingKehadiran->radius}m"
                ], 422);
            }
        }
        
        $jamKerja = SimpegJamKerja::where('is_default', true)->first();
        
        // Handle photo upload
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('absensi/masuk', 'public');
        }

        // Calculate lateness
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
            return response()->json([
                'success' => false, 
                'message' => "Konfigurasi Jenis Kehadiran '{$kodeJenisKehadiran}' tidak ditemukan."
            ], 500);
        }

        try {
            DB::beginTransaction();
            
            $absensiData = [
                'jam_masuk' => $waktuSekarang,
                'latitude_masuk' => $request->latitude,
                'longitude_masuk' => $request->longitude,
                'lokasi_masuk' => optional($settingKehadiran)->nama_gedung ?? 'Lokasi Tidak Diketahui',
                'foto_masuk' => $fotoPath,
                'setting_kehadiran_id' => optional($settingKehadiran)->id,
                'jam_kerja_id' => optional($jamKerja)->id,
                'jenis_kehadiran_id' => optional($jenisKehadiran)->id,
                'rencana_kegiatan' => $request->rencana_kegiatan,
                'status_verifikasi' => 'pending',
                'terlambat' => $isTerlambat,
                'durasi_terlambat' => $durasiTerlambat,
                'metode_absensi' => $metodeAbsensi,
                'check_sum_absensi' => md5($pegawai->id . $today . $waktuSekarang->timestamp)
            ];

            // Add description
            if ($usingQrScan) {
                $absensiData['keterangan'] = 'Scan QR (PIN auto-extracted)' . ($fotoPath ? ' + foto' : '');
            } elseif ($usingManualPin) {
                $absensiData['keterangan'] = 'Manual PIN entry' . ($fotoPath ? ' + foto' : '');
            } else {
                $absensiData['keterangan'] = 'Absensi manual dengan foto';
            }

            $absensi = SimpegAbsensiRecord::updateOrCreate(
                ['pegawai_id' => $pegawai->id, 'tanggal_absensi' => $today],
                $absensiData
            );
            
            DB::commit();
            
            $message = "Absen masuk berhasil";
            if ($usingQrScan) {
                $message .= " (QR Scan)";
            } elseif ($usingManualPin) {
                $message .= " (PIN Manual)";
            }
            if ($isTerlambat) {
                $message .= ". Terlambat {$durasiTerlambat} menit";
            }
            
            return response()->json([
                'success' => true, 
                'message' => $message, 
                'data' => [
                    'id' => $absensi->id,
                    'jam_masuk' => $absensi->jam_masuk->format('H:i:s'),
                    'lokasi' => $absensi->lokasi_masuk,
                    'metode' => $metodeAbsensi,
                    'pin_used' => $pinCode,
                    'terlambat' => $isTerlambat,
                    'durasi_terlambat' => $durasiTerlambat,
                    'dengan_foto' => $fotoPath !== null
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($fotoPath) && $fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            Log::error("Absen Masuk Gagal: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan absensi: ' . $e->getMessage()
            ], 500);
        }
    }

  
    /**
     * ABSEN KELUAR - QR CODE SEBAGAI METODE UTAMA
     */
    public function absenKeluar(Request $request)
    {
        $pegawai = Auth::user()->pegawai;
        $waktuSekarang = Carbon::now();
        $today = $waktuSekarang->toDateString();

        $absensiHariIni = SimpegAbsensiRecord::with('jamKerja')
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal_absensi', $today)
            ->first();
            
        if (!$absensiHariIni || !$absensiHariIni->jam_masuk) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda belum melakukan absen masuk hari ini.'
            ], 422);
        }
        
        if ($absensiHariIni->jam_keluar) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda sudah melakukan absen keluar hari ini.'
            ], 422);
        }

        // Determine method
        $metodeAbsensi = 'manual';
        $pinCode = null;
        $usingQrScan = false;
        $usingManualPin = false;

        $validationRules = [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'realisasi_kegiatan' => 'nullable|string|max:1000'
        ];

        if ($request->has('qr_data') && !empty($request->qr_data)) {
            $validationRules['qr_data'] = 'required|string';
            $validationRules['foto'] = 'nullable|image|mimes:jpeg,png,jpg|max:5120';
            $usingQrScan = true;
        } elseif ($request->has('pin_code') && !empty($request->pin_code)) {
            $validationRules['pin_code'] = 'required|string|min:6|max:8';
            $validationRules['foto'] = 'nullable|image|mimes:jpeg,png,jpg|max:5120';
            $usingManualPin = true;
        } else {
            $validationRules['foto'] = 'required|image|mimes:jpeg,png,jpg|max:5120';
        }
        
        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validasi gagal.', 
                'errors' => $validator->errors()
            ], 422);
        }

        // Process QR or PIN
        $settingKehadiran = null;
        
        try {
            if ($usingQrScan) {
                $decoded = QrPinBundleGenerator::decodePinFromQr($request->qr_data);
                if (!$decoded['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'QR Code tidak valid'
                    ], 400);
                }
                $pinCode = $decoded['pin'];
                $metodeAbsensi = 'qr_scan';
            } elseif ($usingManualPin) {
                $pinCode = $request->pin_code;
                $metodeAbsensi = 'pin_manual';
            }
            
            if ($pinCode) {
                $settingKehadiran = SimpegSettingKehadiran::where('qr_pin_code', $pinCode)
                    ->where('qr_pin_enabled', true)
                    ->first();
                
                if (!$settingKehadiran || $settingKehadiran->isPinExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PIN tidak valid atau sudah kadaluarsa'
                    ], 400);
                }
            } else {
                $settingKehadiran = SimpegSettingKehadiran::first();
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses QR/PIN'
            ], 400);
        }

        // Validate location
        if ($settingKehadiran && $settingKehadiran->wajib_presensi_dilokasi) {
            $isWithinRadius = $settingKehadiran->isWithinRadius($request->latitude, $request->longitude);
            
            if (!$isWithinRadius) {
                $distance = round($settingKehadiran->calculateDistance($request->latitude, $request->longitude), 2);
                return response()->json([
                    'success' => false, 
                    'message' => "Anda berada di luar radius. Jarak: {$distance}m"
                ], 422);
            }
        }

        // Handle photo
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('absensi/keluar', 'public');
        }
        
        $jamMasuk = Carbon::parse($absensiHariIni->jam_masuk);
        $durasi_kerja = (int) round($waktuSekarang->diffInMinutes($jamMasuk));
        $durasiKerjaFormatted = floor($durasi_kerja / 60) . ' jam ' . ($durasi_kerja % 60) . ' menit';

        $isPulangAwal = false;
        $durasiPulangAwal = 0;
        if ($absensiHariIni->jamKerja) {
            $jamPulangString = $absensiHariIni->jamKerja->jam_pulang ?? '17:00:00';
            if ($jamPulangString === '00:00:00' || $jamPulangString === '00:00') {
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
            
            $updateData = [
                'jam_keluar' => $waktuSekarang,
                'latitude_keluar' => $request->latitude,
                'longitude_keluar' => $request->longitude,
                'lokasi_keluar' => optional($settingKehadiran)->nama_gedung ?? 'Lokasi Tidak Diketahui',
                'foto_keluar' => $fotoPath,
                'durasi_kerja' => $durasi_kerja,
                'realisasi_kegiatan' => $request->realisasi_kegiatan,
                'pulang_awal' => $isPulangAwal,
                'durasi_pulang_awal' => $durasiPulangAwal
            ];

            $keterangan = $absensiHariIni->keterangan ?? '';
            if ($usingQrScan) {
                $keterangan .= ' | Keluar: QR Scan' . ($fotoPath ? ' + foto' : '');
            } elseif ($usingManualPin) {
                $keterangan .= ' | Keluar: PIN Manual' . ($fotoPath ? ' + foto' : '');
            } else {
                $keterangan .= ' | Keluar: Manual + foto';
            }
            $updateData['keterangan'] = $keterangan;

            $absensiHariIni->update($updateData);
            
            DB::commit();
            
            $message = "Absen keluar berhasil. Durasi: {$durasiKerjaFormatted}";
            if ($usingQrScan) {
                $message .= " (QR)";
            } elseif ($usingManualPin) {
                $message .= " (PIN)";
            }
            
            return response()->json([
                'success' => true, 
                'message' => $message, 
                'data' => [
                    'id' => $absensiHariIni->id,
                    'jam_keluar' => $absensiHariIni->jam_keluar->format('H:i:s'),
                    'durasi_kerja' => $durasiKerjaFormatted,
                    'lokasi' => $absensiHariIni->lokasi_keluar,
                    'metode' => $metodeAbsensi,
                    'pulang_awal' => $isPulangAwal,
                    'dengan_foto' => $fotoPath !== null
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($fotoPath) && $fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            Log::error("Absen Keluar Gagal: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan absensi keluar: ' . $e->getMessage()
            ], 500);
        }
    }
    // Keep other existing methods...
    public function getHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'nullable|integer|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Filter tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $pegawai = Auth::user()->pegawai;
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
                'tanggal' => Carbon::parse($item->tanggal_absensi)->toDateString(),
                'tanggal_formatted' => Carbon::parse($item->tanggal_absensi)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'jam_masuk' => $item->jam_masuk ? Carbon::parse($item->jam_masuk)->format('H:i') : '-',
                'jam_keluar' => $item->jam_keluar ? Carbon::parse($item->jam_keluar)->format('H:i') : '-',
                'status_label' => $status['label'],
                'status_color' => $status['color'],
                'durasi_kerja' => $item->getFormattedWorkingDuration(),
                'metode_absensi' => $item->metode_absensi ?? 'manual'
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

    public function getDashboardStats(Request $request)
    {
        $pegawai = Auth::user()->pegawai;
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
            'absensi_qr_code' => (clone $query)->where('metode_absensi', 'qr_code')->count(),
            'absensi_manual' => (clone $query)->where('metode_absensi', 'foto')->count(),
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