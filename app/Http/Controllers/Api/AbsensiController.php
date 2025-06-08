<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegSettingKehadiran;
use App\Models\SimpegJenisKehadiran;
use App\Models\SimpegJamKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AbsensiController extends Controller
{
    /**
     * Get absensi status dan info untuk hari ini
     */
    public function getAbsensiStatus()
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        $today = Carbon::now()->format('Y-m-d');
        
        // Cek absensi hari ini
        $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->where('tanggal_absensi', $today)
            ->first();

        // Get setting kehadiran default
        $settingKehadiran = SimpegSettingKehadiran::first();
        
        // Get jam kerja default (jika ada)
        $jamKerja = null;
        if (class_exists('App\Models\SimpegJamKerja')) {
            $jamKerja = SimpegJamKerja::where('is_default', true)
                ->where('is_active', true)
                ->first();
        }

        $currentTime = Carbon::now();
        $currentTimeFormatted = $currentTime->locale('id')->isoFormat('dddd, D MMMM YYYY, HH:mm:ss');
        
        $status = [
            'sudah_absen_masuk' => $absensiHariIni && $absensiHariIni->jam_masuk ? true : false,
            'sudah_absen_keluar' => $absensiHariIni && $absensiHariIni->jam_keluar ? true : false,
            'jam_masuk_tercatat' => $absensiHariIni && $absensiHariIni->jam_masuk ? 
                Carbon::parse($absensiHariIni->jam_masuk)->format('H:i:s') : null,
            'jam_keluar_tercatat' => $absensiHariIni && $absensiHariIni->jam_keluar ? 
                Carbon::parse($absensiHariIni->jam_keluar)->format('H:i:s') : null,
            'tanggal_hari_ini' => $today,
            'waktu_sekarang' => $currentTimeFormatted,
            'jam_kerja_standar' => $jamKerja ? [
                'jam_masuk' => $jamKerja->jam_masuk ? Carbon::parse($jamKerja->jam_masuk)->format('H:i') : '08:00',
                'jam_keluar' => $jamKerja->jam_keluar ? Carbon::parse($jamKerja->jam_keluar)->format('H:i') : '16:00',
                'toleransi_terlambat' => $jamKerja->toleransi_terlambat ?? 15
            ] : [
                'jam_masuk' => '08:00',
                'jam_keluar' => '16:00', 
                'toleransi_terlambat' => $settingKehadiran ? $settingKehadiran->toleransi_terlambat ?? 15 : 15
            ],
            'setting_lokasi' => $settingKehadiran ? [
                'nama_lokasi' => $settingKehadiran->nama_gedung,
                'latitude' => $settingKehadiran->latitude,
                'longitude' => $settingKehadiran->longitude,
                'radius' => $settingKehadiran->radius ?? 100,
                'wajib_foto' => $settingKehadiran->wajib_foto ?? true,
                'wajib_dilokasi' => $settingKehadiran->wajib_presensi_dilokasi ?? true,
                'wajib_rencana_kegiatan' => $settingKehadiran->wajib_isi_rencana_kegiatan ?? false,
                'wajib_realisasi_kegiatan' => $settingKehadiran->wajib_isi_realisasi_kegiatan ?? false
            ] : null
        ];

        return response()->json([
            'success' => true,
            'data' => $status,
            'pegawai_info' => [
                'id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'unit_kerja' => $pegawai->unitKerja ? $pegawai->unitKerja->nama_unit : '-'
            ],
            'message' => 'Status absensi berhasil dimuat'
        ]);
    }



    /**
     * Absen Keluar
     */
 /**
 * Fixed Absen Keluar Method - AbsensiController.php
 */
public function absenKeluar(Request $request)
{
    if (!Auth::check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized - Silakan login terlebih dahulu'
        ], 401);
    }

    $pegawai = Auth::user();
    $today = Carbon::now()->format('Y-m-d');
    
    // Cek apakah sudah absen masuk hari ini
    $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
        ->where('tanggal_absensi', $today)
        ->first();

    if (!$absensiHariIni || !$absensiHariIni->jam_masuk) {
        return response()->json([
            'success' => false,
            'message' => 'Anda belum melakukan absen masuk hari ini'
        ], 422);
    }

    if ($absensiHariIni->jam_keluar) {
        return response()->json([
            'success' => false,
            'message' => 'Anda sudah melakukan absen keluar hari ini pada ' . 
                       Carbon::parse($absensiHariIni->jam_keluar)->format('H:i:s')
        ], 422);
    }

    // Validasi input
    $validator = Validator::make($request->all(), [
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120', // max 5MB
        'realisasi_kegiatan' => 'nullable|string|max:1000',
        'keterangan' => 'nullable|string|max:500'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    // Get setting kehadiran
    $settingKehadiran = SimpegSettingKehadiran::find($absensiHariIni->setting_kehadiran_id) 
                       ?? SimpegSettingKehadiran::first();
    
    // Validasi lokasi jika wajib
    if ($settingKehadiran && $settingKehadiran->wajib_presensi_dilokasi) {
        $jarak = $this->hitungJarak(
            $request->latitude, 
            $request->longitude,
            $settingKehadiran->latitude,
            $settingKehadiran->longitude
        );

        $radiusMax = $settingKehadiran->radius ?? 100;
        
        if ($jarak > $radiusMax) {
            return response()->json([
                'success' => false,
                'message' => "Anda berada di luar radius lokasi kerja. Jarak Anda: " . round($jarak, 2) . "m, maksimal: {$radiusMax}m",
                'data' => [
                    'jarak_anda' => round($jarak, 2),
                    'radius_maksimal' => $radiusMax,
                    'lokasi_kerja' => $settingKehadiran->nama_gedung
                ]
            ], 422);
        }
    }

    // Validasi realisasi kegiatan jika wajib
    if ($settingKehadiran && $settingKehadiran->wajib_isi_realisasi_kegiatan && empty($request->realisasi_kegiatan)) {
        return response()->json([
            'success' => false,
            'message' => 'Realisasi kegiatan wajib diisi'
        ], 422);
    }

    // Upload foto
    $fotoPath = null;
    if ($request->hasFile('foto')) {
        $file = $request->file('foto');
        $fileName = 'absen_keluar_' . $pegawai->id . '_' . date('Y-m-d_H-i-s') . '.' . $file->getClientOriginalExtension();
        $fotoPath = $file->storeAs('absensi/keluar', $fileName, 'public');
    }

    $waktuSekarang = Carbon::now();
    
    // ===== FIXED: Proper calculation for early departure =====
    
    // Get jam kerja default atau dari setting
    $jamKerja = null;
    if (class_exists('App\Models\SimpegJamKerja')) {
        $jamKerja = SimpegJamKerja::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
    
    // Set jam keluar standar
    $jamKeluarDefault = $jamKerja ? $jamKerja->jam_keluar : '16:00:00';
    $jamKeluarStandar = Carbon::createFromFormat('Y-m-d H:i:s', $today . ' ' . $jamKeluarDefault);
    
    // Get toleransi pulang awal
    $toleransiPulangAwal = 15; // default 15 menit
    if ($jamKerja && isset($jamKerja->toleransi_pulang_awal)) {
        $toleransiPulangAwal = $jamKerja->toleransi_pulang_awal;
    } elseif ($settingKehadiran && $settingKehadiran->berlaku_pulang_cepat) {
        $toleransiPulangAwal = $settingKehadiran->toleransi_pulang_cepat ?? 15;
    }
    
    // Hitung pulang awal dengan benar
    $batasWaktuPulang = $jamKeluarStandar->copy()->subMinutes($toleransiPulangAwal);
    $pulangAwal = $waktuSekarang->lt($batasWaktuPulang);
    
    // FIXED: Proper calculation of early departure duration
    $durasi_pulang_awal = 0;
    if ($pulangAwal) {
        // Hitung selisih dalam menit dan pastikan positif
        $durasi_pulang_awal = max(0, $jamKeluarStandar->diffInMinutes($waktuSekarang, false));
        
        // Convert to integer minutes for database
        $durasi_pulang_awal = (int) round($durasi_pulang_awal);
    }

    // FIXED: Proper calculation of work duration
    $jamMasuk = Carbon::parse($absensiHariIni->jam_masuk);
    $durasi_kerja = max(0, $waktuSekarang->diffInMinutes($jamMasuk, false));
    $durasi_kerja = (int) round($durasi_kerja); // Convert to integer minutes

    try {
        DB::beginTransaction();

        // Update record absensi
        $absensiHariIni->update([
            'jam_keluar' => $waktuSekarang,
            'latitude_keluar' => $request->latitude,
            'longitude_keluar' => $request->longitude,
            'lokasi_keluar' => $settingKehadiran ? $settingKehadiran->nama_gedung : 'Tidak Diketahui',
            'foto_keluar' => $fotoPath,
            'pulang_awal' => $pulangAwal,
            'durasi_pulang_awal' => $durasi_pulang_awal, // Now properly calculated integer
            'durasi_kerja' => $durasi_kerja, // Now properly calculated integer
            'realisasi_kegiatan' => $request->realisasi_kegiatan,
            'keterangan' => trim(($absensiHariIni->keterangan ?? '') . ($request->keterangan ? ' | ' . $request->keterangan : ''))
        ]);

        DB::commit();

        $durasiKerjaJam = floor($durasi_kerja / 60);
        $durasiKerjaMenit = $durasi_kerja % 60;
        $durasiKerjaFormatted = sprintf('%d jam %d menit', $durasiKerjaJam, $durasiKerjaMenit);

        $message = $pulangAwal ? 
            "Absen keluar berhasil. Anda pulang {$durasi_pulang_awal} menit lebih awal. Durasi kerja: {$durasiKerjaFormatted}" : 
            "Absen keluar berhasil. Durasi kerja: {$durasiKerjaFormatted}";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => $absensiHariIni->id,
                'tanggal' => $absensiHariIni->tanggal_absensi,
                'jam_masuk' => $absensiHariIni->jam_masuk->format('H:i:s'),
                'jam_keluar' => $absensiHariIni->jam_keluar->format('H:i:s'),
                'jam_keluar_standar' => $jamKeluarStandar->format('H:i:s'),
                'lokasi_masuk' => $absensiHariIni->lokasi_masuk,
                'lokasi_keluar' => $absensiHariIni->lokasi_keluar,
                'latitude_keluar' => $absensiHariIni->latitude_keluar,
                'longitude_keluar' => $absensiHariIni->longitude_keluar,
                'pulang_awal' => $pulangAwal,
                'durasi_pulang_awal' => $durasi_pulang_awal,
                'durasi_kerja' => $durasiKerjaFormatted,
                'durasi_kerja_menit' => $durasi_kerja,
                'foto_masuk_url' => $absensiHariIni->foto_masuk ? Storage::url($absensiHariIni->foto_masuk) : null,
                'foto_keluar_url' => $absensiHariIni->foto_keluar ? Storage::url($absensiHariIni->foto_keluar) : null,
                'jarak_dari_kantor' => $settingKehadiran ? round($this->hitungJarak(
                    $request->latitude, 
                    $request->longitude,
                    $settingKehadiran->latitude,
                    $settingKehadiran->longitude
                ), 2) : null,
                'realisasi_kegiatan' => $absensiHariIni->realisasi_kegiatan,
                'status_verifikasi' => $absensiHariIni->status_verifikasi
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        // Hapus foto jika ada error
        if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
            Storage::disk('public')->delete($fotoPath);
        }

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Get history absensi
     */
    public function getHistory(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        $limit = $request->input('limit', 10);
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        $query = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->with(['settingKehadiran', 'jamKerja']);

        if ($bulan && $tahun) {
            $startDate = Carbon::create($tahun, $bulan, 1);
            $endDate = $startDate->copy()->endOfMonth();
            $query->whereBetween('tanggal_absensi', [$startDate, $endDate]);
        } elseif ($tahun) {
            $query->whereYear('tanggal_absensi', $tahun);
        }

        $absensiHistory = $query->orderBy('tanggal_absensi', 'desc')
            ->limit($limit)
            ->get();

        $data = $absensiHistory->map(function ($absensi) {
            $jamMasuk = $absensi->jam_masuk ? Carbon::parse($absensi->jam_masuk) : null;
            $jamKeluar = $absensi->jam_keluar ? Carbon::parse($absensi->jam_keluar) : null;
            
            $durasi = null;
            if ($jamMasuk && $jamKeluar) {
                $menit = $jamKeluar->diffInMinutes($jamMasuk);
                $jam = floor($menit / 60);
                $sisaMenit = $menit % 60;
                $durasi = sprintf('%d jam %d menit', $jam, $sisaMenit);
            }

            // Calculate distance if coordinates available
            $jarakMasuk = null;
            $jarakKeluar = null;
            if ($absensi->settingKehadiran) {
                if ($absensi->latitude_masuk && $absensi->longitude_masuk) {
                    $jarakMasuk = round($this->hitungJarak(
                        $absensi->latitude_masuk,
                        $absensi->longitude_masuk,
                        $absensi->settingKehadiran->latitude,
                        $absensi->settingKehadiran->longitude
                    ), 2);
                }
                if ($absensi->latitude_keluar && $absensi->longitude_keluar) {
                    $jarakKeluar = round($this->hitungJarak(
                        $absensi->latitude_keluar,
                        $absensi->longitude_keluar,
                        $absensi->settingKehadiran->latitude,
                        $absensi->settingKehadiran->longitude
                    ), 2);
                }
            }

            return [
                'id' => $absensi->id,
                'tanggal' => $absensi->tanggal_absensi,
                'hari' => Carbon::parse($absensi->tanggal_absensi)->locale('id')->isoFormat('dddd'),
                'jam_masuk' => $jamMasuk ? $jamMasuk->format('H:i:s') : null,
                'jam_keluar' => $jamKeluar ? $jamKeluar->format('H:i:s') : null,
                'durasi_kerja' => $durasi,
                'durasi_kerja_menit' => $absensi->durasi_kerja,
                'lokasi_masuk' => $absensi->lokasi_masuk,
                'lokasi_keluar' => $absensi->lokasi_keluar,
                'koordinat_masuk' => [
                    'latitude' => $absensi->latitude_masuk,
                    'longitude' => $absensi->longitude_masuk
                ],
                'koordinat_keluar' => [
                    'latitude' => $absensi->latitude_keluar,
                    'longitude' => $absensi->longitude_keluar
                ],
                'jarak_masuk_meter' => $jarakMasuk,
                'jarak_keluar_meter' => $jarakKeluar,
                'terlambat' => $absensi->terlambat,
                'pulang_awal' => $absensi->pulang_awal,
                'durasi_terlambat' => $absensi->durasi_terlambat,
                'durasi_pulang_awal' => $absensi->durasi_pulang_awal,
                'foto_masuk_url' => $absensi->foto_masuk ? Storage::url($absensi->foto_masuk) : null,
                'foto_keluar_url' => $absensi->foto_keluar ? Storage::url($absensi->foto_keluar) : null,
                'rencana_kegiatan' => $absensi->rencana_kegiatan,
                'realisasi_kegiatan' => $absensi->realisasi_kegiatan,
                'keterangan' => $absensi->keterangan,
                'status_verifikasi' => $absensi->status_verifikasi ?? 'pending',
                'verifikasi_oleh' => $absensi->verifikasi_oleh,
                'verifikasi_at' => $absensi->verifikasi_at,
                'check_sum' => $absensi->check_sum_absensi,
                'status_kehadiran' => $absensi->getAttendanceStatus()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $absensiHistory->count(),
            'message' => 'History absensi berhasil dimuat'
        ]);
    }

    /**
     * Get detail absensi by ID
     */
    public function getDetail($id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        
        $absensi = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->with(['settingKehadiran', 'jamKerja', 'pegawai'])
            ->find($id);

        if (!$absensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data absensi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $absensi->getFullAttendanceInfo(),
            'validation' => $absensi->validateAttendance(),
            'can_be_corrected' => $absensi->canBeCorreected(),
            'checksum_valid' => $absensi->verifyChecksum(),
            'message' => 'Detail absensi berhasil dimuat'
        ]);
    }

    /**
     * Request koreksi absensi
     */
    public function requestCorrection(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        
        $absensi = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->find($id);

        if (!$absensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data absensi tidak ditemukan'
            ], 404);
        }

        if (!$absensi->canBeCorreected()) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi ini tidak dapat dikoreksi (sudah diverifikasi atau melewati batas waktu)'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'alasan_koreksi' => 'required|string|max:1000',
            'jam_masuk_koreksi' => 'nullable|date_format:H:i:s',
            'jam_keluar_koreksi' => 'nullable|date_format:H:i:s',
            'dokumen_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload dokumen pendukung jika ada
        $dokumenPath = null;
        if ($request->hasFile('dokumen_pendukung')) {
            $file = $request->file('dokumen_pendukung');
            $fileName = 'koreksi_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $dokumenPath = $file->storeAs('absensi/koreksi', $fileName, 'public');
        }

        // Create correction request (assuming you have a correction table)
        // This would require creating a new model and migration for corrections
        
        // For now, we'll just update the absensi record with correction request
        $absensi->update([
            'status_verifikasi' => 'correction_requested',
            'keterangan' => ($absensi->keterangan ? $absensi->keterangan . ' | ' : '') . 
                          'KOREKSI: ' . $request->alasan_koreksi
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan koreksi absensi berhasil dikirim',
            'data' => [
                'absensi_id' => $absensi->id,
                'status' => 'correction_requested',
                'alasan' => $request->alasan_koreksi,
                'dokumen_pendukung' => $dokumenPath ? Storage::url($dokumenPath) : null
            ]
        ]);
    }

    /**
     * Verify/Approve absensi (untuk atasan)
     */
    public function verifyAbsensi(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $verifikator = Auth::user();
        
        // Check if user has permission to verify (you might want to add role checking)
        // For now, assuming any authenticated user can verify
        
        $absensi = SimpegAbsensiRecord::with(['pegawai'])->find($id);

        if (!$absensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data absensi tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected',
            'catatan' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $absensi->updateVerificationStatus(
            $request->status, 
            $verifikator->id, 
            $request->catatan
        );

        return response()->json([
            'success' => true,
            'message' => $request->status === 'verified' ? 
                        'Absensi berhasil diverifikasi' : 
                        'Absensi berhasil ditolak',
            'data' => [
                'absensi_id' => $absensi->id,
                'pegawai' => $absensi->pegawai->nama,
                'tanggal' => $absensi->tanggal_absensi,
                'status' => $absensi->status_verifikasi,
                'verifikator' => $verifikator->nama,
                'catatan' => $request->catatan
            ]
        ]);
    }

    /**
     * Get absensi list for verification (untuk atasan)
     */
    public function getAbsensiForVerification(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $limit = $request->input('limit', 20);
        $status = $request->input('status', 'pending');
        $tanggal_mulai = $request->input('tanggal_mulai');
        $tanggal_selesai = $request->input('tanggal_selesai');

        $query = SimpegAbsensiRecord::with(['pegawai', 'settingKehadiran'])
            ->where('status_verifikasi', $status);

        if ($tanggal_mulai && $tanggal_selesai) {
            $query->whereBetween('tanggal_absensi', [$tanggal_mulai, $tanggal_selesai]);
        }

        $absensiList = $query->orderBy('tanggal_absensi', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $absensiList->items(),
            'pagination' => [
                'current_page' => $absensiList->currentPage(),
                'per_page' => $absensiList->perPage(),
                'total' => $absensiList->total(),
                'last_page' => $absensiList->lastPage()
            ],
            'message' => 'Data absensi untuk verifikasi berhasil dimuat'
        ]);
    }

    /**
     * Get statistics dashboard
     */
    public function getDashboardStats(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user();
        $bulan = $request->input('bulan', date('n'));
        $tahun = $request->input('tahun', date('Y'));

        $startDate = Carbon::create($tahun, $bulan, 1);
        $endDate = $startDate->copy()->endOfMonth();

        // Count statistics
        $stats = [
            'total_hari_kerja' => 0,
            'total_hadir' => 0,
            'total_terlambat' => 0,
            'total_pulang_awal' => 0,
            'total_alpha' => 0,
            'rata_rata_durasi_kerja' => 0,
            'status_verifikasi' => [
                'pending' => 0,
                'verified' => 0,
                'rejected' => 0
            ]
        ];

        // Count working days
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            if ($currentDate->dayOfWeek != Carbon::SUNDAY) {
                $stats['total_hari_kerja']++;
            }
            $currentDate->addDay();
        }

        // Get attendance records for the month
        $attendanceRecords = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal_absensi', [$startDate, $endDate])
            ->get();

        $totalDurasiKerja = 0;
        $countDurasiKerja = 0;

        foreach ($attendanceRecords as $record) {
            // Count attendance
            if ($record->jam_masuk) {
                $stats['total_hadir']++;
                
                if ($record->terlambat) {
                    $stats['total_terlambat']++;
                }
                
                if ($record->pulang_awal) {
                    $stats['total_pulang_awal']++;
                }

                if ($record->durasi_kerja) {
                    $totalDurasiKerja += $record->durasi_kerja;
                    $countDurasiKerja++;
                }
            }

            // Count verification status
            $verificationStatus = $record->status_verifikasi ?? 'pending';
            if (isset($stats['status_verifikasi'][$verificationStatus])) {
                $stats['status_verifikasi'][$verificationStatus]++;
            }
        }

        // Calculate missing days as alpha
        $stats['total_alpha'] = $stats['total_hari_kerja'] - $stats['total_hadir'];

        // Calculate average working duration
        if ($countDurasiKerja > 0) {
            $avgMinutes = $totalDurasiKerja / $countDurasiKerja;
            $avgHours = floor($avgMinutes / 60);
            $avgMins = $avgMinutes % 60;
            $stats['rata_rata_durasi_kerja'] = sprintf('%d jam %d menit', $avgHours, $avgMins);
        }

        // Calculate percentages
        if ($stats['total_hari_kerja'] > 0) {
            $stats['persentase_kehadiran'] = round(($stats['total_hadir'] / $stats['total_hari_kerja']) * 100, 2);
            $stats['persentase_keterlambatan'] = round(($stats['total_terlambat'] / $stats['total_hari_kerja']) * 100, 2);
        } else {
            $stats['persentase_kehadiran'] = 0;
            $stats['persentase_keterlambatan'] = 0;
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'nama_bulan' => Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM'),
                'tanggal_mulai' => $startDate->format('Y-m-d'),
                'tanggal_selesai' => $endDate->format('Y-m-d')
            ],
            'message' => 'Statistik dashboard berhasil dimuat'
        ]);
    }
    

    private function hitungJarak($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Jarak dalam meter
    }
    public function absenMasuk(Request $request)
{
    if (!Auth::check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized - Silakan login terlebih dahulu'
        ], 401);
    }

    $pegawai = Auth::user();
    $today = Carbon::now()->format('Y-m-d');
    
    // Cek apakah sudah absen masuk hari ini
    $absensiHariIni = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
        ->where('tanggal_absensi', $today)
        ->first();

    if ($absensiHariIni && $absensiHariIni->jam_masuk) {
        return response()->json([
            'success' => false,
            'message' => 'Anda sudah melakukan absen masuk hari ini pada ' . 
                       Carbon::parse($absensiHariIni->jam_masuk)->format('H:i:s')
        ], 422);
    }

    // Validasi input
    $validator = Validator::make($request->all(), [
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120', // max 5MB
        'rencana_kegiatan' => 'nullable|string|max:1000',
        'keterangan' => 'nullable|string|max:500'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    // Get setting kehadiran
    $settingKehadiran = SimpegSettingKehadiran::first();
    
    // Validasi lokasi jika wajib
    if ($settingKehadiran && $settingKehadiran->wajib_presensi_dilokasi) {
        $jarak = $this->hitungJarak(
            $request->latitude, 
            $request->longitude,
            $settingKehadiran->latitude,
            $settingKehadiran->longitude
        );

        $radiusMax = $settingKehadiran->radius ?? 100; // default 100 meter
        
        if ($jarak > $radiusMax) {
            return response()->json([
                'success' => false,
                'message' => "Anda berada di luar radius lokasi kerja. Jarak Anda: " . round($jarak, 2) . "m, maksimal: {$radiusMax}m",
                'data' => [
                    'jarak_anda' => round($jarak, 2),
                    'radius_maksimal' => $radiusMax,
                    'lokasi_kerja' => $settingKehadiran->nama_gedung
                ]
            ], 422);
        }
    }

    // Validasi rencana kegiatan jika wajib
    if ($settingKehadiran && $settingKehadiran->wajib_isi_rencana_kegiatan && empty($request->rencana_kegiatan)) {
        return response()->json([
            'success' => false,
            'message' => 'Rencana kegiatan wajib diisi'
        ], 422);
    }

    // Upload foto
    $fotoPath = null;
    if ($request->hasFile('foto')) {
        $file = $request->file('foto');
        $fileName = 'absen_masuk_' . $pegawai->id . '_' . date('Y-m-d_H-i-s') . '.' . $file->getClientOriginalExtension();
        $fotoPath = $file->storeAs('absensi/masuk', $fileName, 'public');
    }

    $waktuSekarang = Carbon::now();
    
    // ===== FIXED: Proper calculation for late attendance =====
    
    // Get jam kerja default atau dari setting
    $jamKerja = null;
    if (class_exists('App\Models\SimpegJamKerja')) {
        $jamKerja = SimpegJamKerja::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
    
    // Set jam masuk standar
    $jamMasukDefault = $jamKerja ? $jamKerja->jam_masuk : '08:00:00';
    $jamMasukStandar = Carbon::createFromFormat('Y-m-d H:i:s', $today . ' ' . $jamMasukDefault);
    
    // Get toleransi terlambat
    $toleransi = 15; // default 15 menit
    if ($jamKerja && isset($jamKerja->toleransi_terlambat)) {
        $toleransi = $jamKerja->toleransi_terlambat;
    } elseif ($settingKehadiran && $settingKehadiran->berlaku_keterlambatan) {
        $toleransi = $settingKehadiran->toleransi_terlambat ?? 15;
    }
    
    // Hitung keterlambatan dengan benar
    $batasWaktu = $jamMasukStandar->copy()->addMinutes($toleransi);
    $terlambat = $waktuSekarang->gt($batasWaktu);
    
    // FIXED: Proper calculation of late duration
    $durasi_terlambat = 0;
    if ($terlambat) {
        // Hitung selisih dalam menit dan pastikan positif
        $durasi_terlambat = max(0, $waktuSekarang->diffInMinutes($jamMasukStandar, false));
        
        // Jika lebih dari 12 jam (720 menit), kemungkinan absen hari berikutnya
        if ($durasi_terlambat > 720) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu absen tidak valid. Silakan hubungi admin jika Anda perlu melakukan absen di luar jam kerja normal.',
                'data' => [
                    'waktu_sekarang' => $waktuSekarang->format('H:i:s'),
                    'jam_masuk_standar' => $jamMasukStandar->format('H:i:s'),
                    'durasi_terlambat' => $durasi_terlambat . ' menit'
                ]
            ], 422);
        }
        
        // Convert to integer minutes for database
        $durasi_terlambat = (int) round($durasi_terlambat);
    }

    try {
        DB::beginTransaction();

        // Simpan atau update record absensi
        if ($absensiHariIni) {
            // Update existing record
            $absensiHariIni->update([
                'jam_masuk' => $waktuSekarang,
                'latitude_masuk' => $request->latitude,
                'longitude_masuk' => $request->longitude,
                'lokasi_masuk' => $settingKehadiran ? $settingKehadiran->nama_gedung : 'Tidak Diketahui',
                'foto_masuk' => $fotoPath,
                'terlambat' => $terlambat,
                'durasi_terlambat' => $durasi_terlambat, // Now properly calculated integer
                'setting_kehadiran_id' => $settingKehadiran ? $settingKehadiran->id : null,
                'rencana_kegiatan' => $request->rencana_kegiatan,
                'keterangan' => $request->keterangan,
                'status_verifikasi' => 'pending',
                'check_sum_absensi' => md5($pegawai->id . $today . $waktuSekarang->timestamp)
            ]);
            
            $absensi = $absensiHariIni;
        } else {
            // Create new record
            $absensi = SimpegAbsensiRecord::create([
                'pegawai_id' => $pegawai->id,
                'tanggal_absensi' => $today,
                'jam_masuk' => $waktuSekarang,
                'latitude_masuk' => $request->latitude,
                'longitude_masuk' => $request->longitude,
                'lokasi_masuk' => $settingKehadiran ? $settingKehadiran->nama_gedung : 'Tidak Diketahui',
                'foto_masuk' => $fotoPath,
                'terlambat' => $terlambat,
                'durasi_terlambat' => $durasi_terlambat, // Now properly calculated integer
                'setting_kehadiran_id' => $settingKehadiran ? $settingKehadiran->id : null,
                'rencana_kegiatan' => $request->rencana_kegiatan,
                'keterangan' => $request->keterangan,
                'status_verifikasi' => 'pending',
                'check_sum_absensi' => md5($pegawai->id . $today . $waktuSekarang->timestamp)
            ]);
        }

        DB::commit();

        $message = $terlambat ? 
            "Absen masuk berhasil. Anda terlambat {$durasi_terlambat} menit." : 
            "Absen masuk berhasil.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => $absensi->id,
                'tanggal' => $absensi->tanggal_absensi,
                'jam_masuk' => $absensi->jam_masuk->format('H:i:s'),
                'jam_masuk_standar' => $jamMasukStandar->format('H:i:s'),
                'lokasi' => $absensi->lokasi_masuk,
                'latitude' => $absensi->latitude_masuk,
                'longitude' => $absensi->longitude_masuk,
                'terlambat' => $terlambat,
                'durasi_terlambat' => $durasi_terlambat,
                'foto_url' => $absensi->foto_masuk ? Storage::url($absensi->foto_masuk) : null,
                'jarak_dari_kantor' => $settingKehadiran ? round($this->hitungJarak(
                    $request->latitude, 
                    $request->longitude,
                    $settingKehadiran->latitude,
                    $settingKehadiran->longitude
                ), 2) : null,
                'rencana_kegiatan' => $absensi->rencana_kegiatan,
                'status_verifikasi' => $absensi->status_verifikasi
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        // Hapus foto jika ada error
        if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
            Storage::disk('public')->delete($fotoPath);
        }

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage()
        ], 500);
    }
}
}