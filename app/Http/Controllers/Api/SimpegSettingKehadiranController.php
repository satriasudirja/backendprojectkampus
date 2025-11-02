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
        try {
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

            $data = $request->all();
            
            $booleanFields = [
                'berlaku_keterlambatan',
                'berlaku_pulang_cepat', 
                'wajib_foto',
                'wajib_isi_rencana_kegiatan',
                'wajib_isi_realisasi_kegiatan',
                'wajib_presensi_dilokasi',
                'qr_code_enabled',
                'qr_pin_enabled'
            ];

            foreach ($booleanFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = false;
                }
            }

            if (!isset($data['toleransi_terlambat'])) {
                $data['toleransi_terlambat'] = 15;
            }
            
            if (!isset($data['toleransi_pulang_cepat'])) {
                $data['toleransi_pulang_cepat'] = 15;
            }

            $existingSetting = SimpegSettingKehadiran::active()->first();

            DB::beginTransaction();

            try {
                if ($existingSetting) {
                    $oldData = $existingSetting->getOriginal();
                    $existingSetting->update($data);

                    \Log::info('Updating existing setting, QR enabled: ' . ($data['qr_code_enabled'] ? 'YES' : 'NO'));
                    \Log::info('PIN enabled: ' . ($data['qr_pin_enabled'] ? 'YES' : 'NO'));

                    $needRegenerate = false;
                    
                    if ($data['qr_code_enabled'] || $data['qr_pin_enabled']) {
                        $isQrMissing = is_null($existingSetting->qr_code_path) || is_null($existingSetting->qr_code_token);
                        $isPinMissing = $data['qr_pin_enabled'] && is_null($existingSetting->qr_pin_code);
                        $locationChanged = $oldData['latitude'] != $data['latitude'] || 
                                        $oldData['longitude'] != $data['longitude'] ||
                                        $oldData['nama_gedung'] != $data['nama_gedung'];
                        $qrJustEnabled = !$oldData['qr_code_enabled'] && $data['qr_code_enabled'];
                        $pinJustEnabled = !$oldData['qr_pin_enabled'] && $data['qr_pin_enabled'];
                        
                        $needRegenerate = $isQrMissing || $isPinMissing || $locationChanged || $qrJustEnabled || $pinJustEnabled;
                        
                        \Log::info('Regenerate Check:', [
                            'qr_missing' => $isQrMissing,
                            'pin_missing' => $isPinMissing,
                            'location_changed' => $locationChanged,
                            'qr_just_enabled' => $qrJustEnabled,
                            'pin_just_enabled' => $pinJustEnabled,
                            'need_regenerate' => $needRegenerate
                        ]);
                    }
                    
                    if ($needRegenerate) {
                        \Log::info('Regenerating QR Code and PIN...');
                        $qrResult = $existingSetting->regenerateQrCode();
                        \Log::info('Regenerate result: ' . ($qrResult ? 'SUCCESS' : 'FAILED'));
                    }
                    
                    ActivityLogger::log('update', $existingSetting, $oldData);

                    DB::commit();
                    $existingSetting->refresh();

                    return response()->json([
                        'success' => true,
                        'data' => $this->formatSettingResponse($existingSetting),
                        'mode' => 'updated',
                        'message' => 'Setting kehadiran berhasil diperbarui'
                    ]);

                } else {
                    \Log::info('Creating new setting');
                    
                    $setting = SimpegSettingKehadiran::create($data);
                    \Log::info('Setting created with ID: ' . $setting->id);
                
                    if ($setting->qr_code_enabled || $setting->qr_pin_enabled) {
                        \Log::info('Generating QR Code and PIN...');
                        $qrResult = $setting->regenerateQrCode();
                        \Log::info('Generation result: ' . ($qrResult ? 'SUCCESS' : 'FAILED'));
                    }
                    
                    ActivityLogger::log('create', $setting, $setting->toArray());

                    DB::commit();
                    $setting->refresh();

                    return response()->json([
                        'success' => true,
                        'data' => $this->formatSettingResponse($setting),
                        'mode' => 'created',
                        'message' => 'Setting kehadiran berhasil dibuat'
                    ], 201);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Transaction error: ' . $e->getMessage());
                throw $e;
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan setting kehadiran: ' . $e->getMessage()
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