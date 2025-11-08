<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegSettingKehadiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SimpegSettingKehadiranController extends Controller
{
    public function index()
    {
        try {
            $setting = SimpegSettingKehadiran::active()->first();
            
            if (!$setting) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'mode' => 'create',
                    'message' => 'Belum ada setting kehadiran. Silakan buat setting baru.',
                    'form_template' => $this->getFormTemplate(),
                    'validation_rules' => $this->getValidationRules()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatSettingResponse($setting),
                'mode' => 'edit',
                'message' => 'Setting kehadiran ditemukan',
                'form_template' => $this->getFormTemplate(),
                'validation_rules' => $this->getValidationRules()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil setting kehadiran: ' . $e->getMessage()
            ], 500);
        }
    }


  
    public function store(Request $request)
    {
        // 1. Validasi di luar try-catch (Best Practice)
        // Jika validasi gagal, kita tidak perlu membatalkan transaksi DB.
        $validator = Validator::make($request->all(), [
            'nama_gedung' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:1|max:5000',
            'berlaku_keterlambatan' => 'boolean',
            'toleransi_terlambat' => 'nullable|integer|min:0|max:120',
            'berlaku_pulang_cepat' => 'boolean',
            'toleransi_pulang_cepat' => 'nullable|integer|min:0|max:120',
            'wajib_foto' => 'boolean',
            'wajib_isi_rencana_kegiatan' => 'boolean',
            'wajib_isi_realisasi_kegiatan' => 'boolean',
            'wajib_presensi_dilokasi' => 'boolean',
            'qr_code_enabled' => 'boolean',
            'qr_pin_enabled' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validasi gagal'
            ], 422);
        }

        // 2. Gunakan SATU blok try-catch untuk semua operasi database
        try {
            $data = $request->all();
            
            // Logika untuk menangani boolean (checkbox)
            $booleanFields = [
                'berlaku_keterlambatan', 'berlaku_pulang_cepat', 'wajib_foto',
                'wajib_isi_rencana_kegiatan', 'wajib_isi_realisasi_kegiatan',
                'wajib_presensi_dilokasi', 'qr_code_enabled', 'qr_pin_enabled'
            ];

            // Cara yang lebih bersih untuk mengatur default 'false' jika tidak ada
            foreach ($booleanFields as $field) {
                $data[$field] = $request->input($field, false); 
            }

            // Cara yang lebih bersih untuk mengatur default '15'
            $data['toleransi_terlambat'] = $request->input('toleransi_terlambat', 15);
            $data['toleransi_pulang_cepat'] = $request->input('toleransi_pulang_cepat', 15);

            // Mulai Transaksi Database
            DB::beginTransaction();
            
            \Log::info('Creating new setting...');
                    
            $setting = SimpegSettingKehadiran::create($data);
            \Log::info('Setting created with ID: ' . $setting->id);
        
            // Logika generate QR/PIN Anda sudah benar
            if ($setting->qr_code_enabled || $setting->qr_pin_enabled) {
                \Log::info('Generating QR Code and PIN...');
                $qrResult = $setting->regenerateQrCode(); // Diasumsikan method ini ada di model
                \Log::info('Generation result: ' . ($qrResult ? 'SUCCESS' : 'FAILED'));
            }
            
            ActivityLogger::log('create', $setting, $setting->toArray());

            // Jika semua berhasil, simpan permanen
            DB::commit();
            
            // Refresh model untuk mendapatkan data terbaru (terutama QR path setelah save)
            $setting->refresh(); 

            return response()->json([
                'success' => true,
                'data' => $this->formatSettingResponse($setting),
                'mode' => 'created',
                'message' => 'Setting kehadiran berhasil dibuat'
            ], 201); // 201 adalah status code yang tepat untuk 'Created'

        } catch (\Exception $e) {
            // Jika ada error di mana saja di dalam 'try', batalkan semua perubahan DB
            DB::rollBack();

            // Catat error dengan detail lengkap untuk debugging
            \Log::error('Gagal menyimpan setting kehadiran:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Ini sangat penting untuk debug
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan setting kehadiran: Terjadi kesalahan pada server.'
                // 'message' => 'Gagal menyimpan setting kehadiran: ' . $e->getMessage() // (Hanya untuk mode debug)
            ], 500);
        }
    }
    
    /**
     * Memperbarui setting kehadiran yang sudah ada.
     * * @param  \Illuminate\Http\Request  $request
     * @param  string  $id  (ID dari setting yang akan di-update)
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // 1. Validasi
        // Menggunakan rules yang sama dengan 'store'
        $validator = Validator::make($request->all(), [
            'nama_gedung' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:1|max:5000',
            'berlaku_keterlambatan' => 'boolean',
            'toleransi_terlambat' => 'nullable|integer|min:0|max:120',
            'berlaku_pulang_cepat' => 'boolean',
            'toleransi_pulang_cepat' => 'nullable|integer|min:0|max:120',
            'wajib_foto' => 'boolean',
            'wajib_isi_rencana_kegiatan' => 'boolean',
            'wajib_isi_realisasi_kegiatan' => 'boolean',
            'wajib_presensi_dilokasi' => 'boolean',
            'qr_code_enabled' => 'boolean',
            'qr_pin_enabled' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validasi gagal'
            ], 422);
        }

        // 2. Cari Model
        $setting = SimpegSettingKehadiran::active()->find($id);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting kehadiran dengan ID ' . $id . ' tidak ditemukan'
            ], 404); // 404 Not Found
        }

        // 3. Transaksi Database
        try {
            $data = $request->all();
            
            // Logika untuk menangani boolean (checkbox)
            $booleanFields = [
                'berlaku_keterlambatan', 'berlaku_pulang_cepat', 'wajib_foto',
                'wajib_isi_rencana_kegiatan', 'wajib_isi_realisasi_kegiatan',
                'wajib_presensi_dilokasi', 'qr_code_enabled', 'qr_pin_enabled'
            ];
            foreach ($booleanFields as $field) {
                $data[$field] = $request->input($field, false);
            }
            
            // Logika untuk menangani default '15'
            $data['toleransi_terlambat'] = $request->input('toleransi_terlambat', 15);
            $data['toleransi_pulang_cepat'] = $request->input('toleransi_pulang_cepat', 15);

            // Mulai Transaksi
            DB::beginTransaction();
            
            // Simpan data lama untuk logging
            $oldData = $setting->getOriginal();
            
            // Update data setting
            $setting->update($data);
            \Log::info('Setting updated for ID: ' . $setting->id);

            // Cek kondisi untuk regenerate QR/PIN
            $isQrMissing = is_null($oldData['qr_code_path']);
            $qrToggledOn = !$oldData['qr_code_enabled'] && $setting->qr_code_enabled;
            $pinToggledOn = !$oldData['qr_pin_enabled'] && $setting->qr_pin_enabled;
            $locationChanged = $oldData['latitude'] != $data['latitude'] ||
                               $oldData['longitude'] != $data['longitude'] ||
                               $oldData['nama_gedung'] != $data['nama_gedung'];

            // Regenerate jika:
            // 1. QR atau PIN diaktifkan DAN
            // 2. (File QR-nya hilang ATAU lokasinya berubah ATAU QR-nya baru dinyalakan ATAU PIN-nya baru dinyalakan)
            if (($setting->qr_code_enabled || $setting->qr_pin_enabled) && 
                ($isQrMissing || $locationChanged || $qrToggledOn || $pinToggledOn)) 
            {
                \Log::info('Regenerating QR Code/PIN for update...', ['id' => $setting->id]);
                $qrResult = $setting->regenerateQrCode();
                \Log::info('Generation result: ' . ($qrResult ? 'SUCCESS' : 'FAILED'));
            }

            ActivityLogger::log('update', $setting, $oldData);

            // Jika semua berhasil, simpan permanen
            DB::commit();
            
            // Kembalikan data yang sudah fresh (terbaru)
            return response()->json([
                'success' => true,
                'data' => $this->formatSettingResponse($setting->fresh()),
                'mode' => 'updated',
                'message' => 'Setting kehadiran berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            // Jika ada error, batalkan semua perubahan DB
            DB::rollBack();

            \Log::error('Gagal memperbarui setting kehadiran:', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui setting: Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    // NEW: Download QR Code
    public function downloadQrCode($id)
    {
        try {
            $setting = SimpegSettingKehadiran::active()->find($id);

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting kehadiran tidak ditemukan'
                ], 404);
            }

            if (!$setting->qr_code_enabled || !$setting->qr_code_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code tidak tersedia'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $setting->qr_code_path);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File QR Code tidak ditemukan'
                ], 404);
            }

            $fileName = 'QR_' . str_replace(' ', '_', $setting->nama_gedung) . '_' . date('Ymd') . '.png';

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'image/png'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal download QR Code: ' . $e->getMessage()
            ], 500);
        }
    }

    // NEW: Regenerate QR Code
    public function regenerateQrCode($id)
    {
        try {
            $setting = SimpegSettingKehadiran::active()->find($id);

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting kehadiran tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            $result = $setting->regenerateQrCode();

            if (!$result) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal regenerate QR Code dan PIN'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'QR Code dan PIN berhasil digenerate ulang',
                'data' => [
                    'qr_code_url' => $setting->getQrCodeUrl(),
                    'pin_code' => $setting->qr_pin_enabled ? $setting->qr_pin_code : null,
                    'generated_at' => $setting->qr_code_generated_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal regenerate: ' . $e->getMessage()
            ], 500);
        }
    }


    // NEW: Regenerate PIN only
    public function regeneratePinCode($id)
    {
        try {
            $setting = SimpegSettingKehadiran::active()->find($id);

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting kehadiran tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            $result = $setting->regeneratePinCode();

            if (!$result) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal regenerate PIN'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PIN berhasil digenerate ulang',
                'data' => [
                    'pin_code' => $setting->qr_pin_code,
                    'pin_expires_at' => $setting->qr_pin_expires_at?->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal regenerate PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    // NEW: Validate QR Code
    public function validateQrCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_token' => 'required_without:pin_code|string',
            'pin_code' => 'required_without:qr_token|string|min:6|max:8',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $setting = null;
            $methodUsed = null;

            // Try QR Token first
            if ($request->has('qr_token') && !empty($request->qr_token)) {
                $qrData = json_decode($request->qr_token, true);
                
                if ($qrData && isset($qrData['token'])) {
                    $setting = SimpegSettingKehadiran::active()
                        ->where('qr_code_token', $qrData['token'])
                        ->first();
                    $methodUsed = 'qr_code';
                }
            }
            
            // Try PIN if QR failed or not provided
            if (!$setting && $request->has('pin_code') && !empty($request->pin_code)) {
                $setting = SimpegSettingKehadiran::active()
                    ->where('qr_pin_code', $request->pin_code)
                    ->where('qr_pin_enabled', true)
                    ->first();
                
                if ($setting && $setting->isPinExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PIN sudah kadaluarsa. Silakan minta PIN baru.'
                    ], 400);
                }
                
                $methodUsed = 'pin_code';
            }

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code atau PIN tidak ditemukan atau sudah tidak berlaku'
                ], 404);
            }

            // Validate location if provided
            $locationValid = true;
            $distance = null;

            if ($request->has('latitude') && $request->has('longitude')) {
                if ($setting->wajib_presensi_dilokasi) {
                    $locationValid = $setting->isWithinRadius($request->latitude, $request->longitude);
                    $distance = $setting->calculateDistance($request->latitude, $request->longitude);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Validasi berhasil',
                'data' => [
                    'setting_id' => $setting->id,
                    'nama_gedung' => $setting->nama_gedung,
                    'method_used' => $methodUsed,
                    'location_valid' => $locationValid,
                    'distance' => $distance ? round($distance, 2) : null,
                    'max_radius' => $setting->radius,
                    'requirements' => [
                        'wajib_foto' => $setting->wajib_foto,
                        'wajib_lokasi' => $setting->wajib_presensi_dilokasi,
                        'wajib_rencana_kegiatan' => $setting->wajib_isi_rencana_kegiatan
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal validasi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id = null)
    {
        try {
            if ($id === null) {
                $setting = SimpegSettingKehadiran::active()->first();
            } else {
                $setting = SimpegSettingKehadiran::active()->find($id);
            }

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting kehadiran tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatSettingResponse($setting),
                'location_info' => $setting->getLocationInfo()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail setting: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatSettingResponse($setting)
    {
        if (!$setting) {
            return null;
        }

        return [
            'id' => $setting->id,
            'nama_gedung' => $setting->nama_gedung,
            'coordinates' => [
                'latitude' => $setting->latitude,
                'longitude' => $setting->longitude
            ],
            'radius' => $setting->radius,
            'qr_code' => [
                'enabled' => $setting->qr_code_enabled,
                'url' => $setting->getQrCodeUrl(),
                'generated_at' => $setting->qr_code_generated_at?->format('Y-m-d H:i:s'),
                'download_url' => $setting->qr_code_enabled ? route('api.setting-kehadiran.download-qr', $setting->id) : null
            ],
            'pin_code' => [
                'enabled' => $setting->qr_pin_enabled,
                'code' => $setting->qr_pin_enabled ? $setting->qr_pin_code : null,
                'expires_at' => $setting->qr_pin_expires_at?->format('Y-m-d H:i:s'),
                'is_expired' => $setting->isPinExpired()
            ],
            'late_rules' => [
                'berlaku_keterlambatan' => $setting->berlaku_keterlambatan,
                'toleransi_terlambat' => $setting->toleransi_terlambat
            ],
            'early_leave_rules' => [
                'berlaku_pulang_cepat' => $setting->berlaku_pulang_cepat,
                'toleransi_pulang_cepat' => $setting->toleransi_pulang_cepat
            ],
            'attendance_requirements' => [
                'wajib_foto' => $setting->wajib_foto,
                'wajib_isi_rencana_kegiatan' => $setting->wajib_isi_rencana_kegiatan,
                'wajib_isi_realisasi_kegiatan' => $setting->wajib_isi_realisasi_kegiatan,
                'wajib_presensi_dilokasi' => $setting->wajib_presensi_dilokasi
            ],
            'maps_url' => $setting->maps_url,
            'created_at' => $setting->created_at,
            'updated_at' => $setting->updated_at
        ];
    }

    private function getFormTemplate()
    {
        return [
            'nama_gedung' => [
                'type' => 'text',
                'label' => 'Nama Gedung',
                'placeholder' => 'Masukkan nama gedung/lokasi',
                'required' => true,
                'default' => 'Kampus Utama'
            ],
            'latitude' => [
                'type' => 'number',
                'label' => 'Latitude',
                'placeholder' => 'Contoh: -6.559890',
                'step' => 'any',
                'required' => true,
                'default' => -6.559890
            ],
            'longitude' => [
                'type' => 'number', 
                'label' => 'Longitude',
                'placeholder' => 'Contoh: 106.792960',
                'step' => 'any',
                'required' => true,
                'default' => 106.792960
            ],
            'radius' => [
                'type' => 'number',
                'label' => 'Radius Presensi (meter)',
                'placeholder' => 'Masukkan radius dalam meter',
                'min' => 1,
                'max' => 5000,
                'required' => true,
                'default' => 200
            ],
            'qr_code_enabled' => [
                'type' => 'checkbox',
                'label' => 'Aktifkan QR Code',
                'description' => 'Gunakan QR Code untuk presensi',
                'default' => false
            ],
            'qr_pin_enabled' => [
                'type' => 'checkbox',
                'label' => 'Aktifkan PIN Code',
                'description' => 'Alternatif 6-digit PIN jika tidak bisa scan QR',
                'default' => true
            ],
            'berlaku_keterlambatan' => [
                'type' => 'checkbox',
                'label' => 'Berlaku Keterlambatan',
                'description' => 'Aktifkan validasi keterlambatan',
                'default' => true
            ],
            'toleransi_terlambat' => [
                'type' => 'number',
                'label' => 'Toleransi Terlambat (menit)',
                'placeholder' => 'Masukkan toleransi dalam menit',
                'min' => 0,
                'max' => 120,
                'default' => 15,
                'depends_on' => 'berlaku_keterlambatan'
            ],
            'berlaku_pulang_cepat' => [
                'type' => 'checkbox',
                'label' => 'Berlaku Pulang Cepat',
                'description' => 'Aktifkan validasi pulang cepat',
                'default' => true
            ],
            'toleransi_pulang_cepat' => [
                'type' => 'number',
                'label' => 'Toleransi Pulang Cepat (menit)',
                'placeholder' => 'Masukkan toleransi dalam menit',
                'min' => 0,
                'max' => 120,
                'default' => 15,
                'depends_on' => 'berlaku_pulang_cepat'
            ],
            'wajib_foto' => [
                'type' => 'checkbox',
                'label' => 'Wajib Foto',
                'description' => 'Wajib upload foto saat presensi',
                'default' => true
            ],
            'wajib_isi_rencana_kegiatan' => [
                'type' => 'checkbox',
                'label' => 'Wajib Isi Rencana Kegiatan',
                'description' => 'Wajib mengisi rencana kegiatan saat masuk',
                'default' => false
            ],
            'wajib_isi_realisasi_kegiatan' => [
                'type' => 'checkbox',
                'label' => 'Wajib Isi Realisasi Kegiatan',
                'description' => 'Wajib mengisi realisasi kegiatan saat keluar',
                'default' => false
            ],
            'wajib_presensi_dilokasi' => [
                'type' => 'checkbox',
                'label' => 'Wajib Presensi di Lokasi',
                'description' => 'Presensi hanya bisa dilakukan di dalam radius',
                'default' => true
            ]
        ];
    }

    private function getValidationRules()
    {
        return [
            'nama_gedung' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:1|max:5000',
            'berlaku_keterlambatan' => 'boolean',
            'toleransi_terlambat' => 'nullable|integer|min:0|max:120',
            'berlaku_pulang_cepat' => 'boolean',
            'toleransi_pulang_cepat' => 'nullable|integer|min:0|max:120',
            'wajib_foto' => 'boolean',
            'wajib_isi_rencana_kegiatan' => 'boolean',
            'wajib_isi_realisasi_kegiatan' => 'boolean',
            'wajib_presensi_dilokasi' => 'boolean',
            'qr_code_enabled' => 'boolean',
            'qr_pin_enabled' => 'boolean',
        ];
    }
}