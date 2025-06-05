<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegSettingKehadiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class SimpegSettingKehadiranController extends Controller
{
    // Get setting kehadiran (show form with existing data or empty form)
    public function index()
    {
        try {
            // Cari setting yang ada (ambil yang pertama karena harusnya hanya ada 1)
            $setting = SimpegSettingKehadiran::active()->first();
            
            // Jika tidak ada setting, return template kosong untuk create
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

            // Jika ada setting, return data untuk edit
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

    // Save setting (auto create/update)
    public function store(Request $request)
    {
        try {
            // Validasi input
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
                'wajib_presensi_dilokasi' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validasi gagal'
                ], 422);
            }

            $data = $request->all();
            
            // Set default values untuk boolean fields jika tidak diset
            $booleanFields = [
                'berlaku_keterlambatan',
                'berlaku_pulang_cepat', 
                'wajib_foto',
                'wajib_isi_rencana_kegiatan',
                'wajib_isi_realisasi_kegiatan',
                'wajib_presensi_dilokasi'
            ];

            foreach ($booleanFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = false;
                }
            }

            // Set default toleransi jika tidak diset
            if (!isset($data['toleransi_terlambat']) || $data['toleransi_terlambat'] === null) {
                $data['toleransi_terlambat'] = 15; // Default 15 menit
            }
            
            if (!isset($data['toleransi_pulang_cepat']) || $data['toleransi_pulang_cepat'] === null) {
                $data['toleransi_pulang_cepat'] = 15; // Default 15 menit
            }

            // Cek apakah sudah ada setting
            $existingSetting = SimpegSettingKehadiran::active()->first();

            DB::beginTransaction();

            if ($existingSetting) {
                // UPDATE existing setting
                $oldData = $existingSetting->getOriginal();
                $existingSetting->update($data);
                
                ActivityLogger::log('update', $existingSetting, $oldData);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => $this->formatSettingResponse($existingSetting->fresh()),
                    'mode' => 'updated',
                    'message' => 'Setting kehadiran berhasil diperbarui'
                ]);

            } else {
                // CREATE new setting
                $setting = SimpegSettingKehadiran::create($data);
                
                ActivityLogger::log('create', $setting, $setting->toArray());

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => $this->formatSettingResponse($setting),
                    'mode' => 'created',
                    'message' => 'Setting kehadiran berhasil dibuat'
                ], 201);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan setting kehadiran: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get detail setting
    public function show($id = null)
    {
        try {
            // Jika tidak ada ID, ambil setting aktif pertama
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
                'location_info' => $setting->getLocationInfo(),
                'validation_info' => [
                    'can_validate_late' => $setting->berlaku_keterlambatan,
                    'can_validate_early_leave' => $setting->berlaku_pulang_cepat,
                    'requires_photo' => $setting->wajib_foto,
                    'requires_location' => $setting->wajib_presensi_dilokasi,
                    'requires_activity_plan' => $setting->wajib_isi_rencana_kegiatan,
                    'requires_activity_report' => $setting->wajib_isi_realisasi_kegiatan
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail setting: ' . $e->getMessage()
            ], 500);
        }
    }

    // Reset setting to default values
    public function resetToDefault()
    {
        try {
            $defaultData = [
                'nama_gedung' => 'Kampus Utama',
                'latitude' => -6.559890,
                'longitude' => 106.792960,
                'radius' => 200,
                'berlaku_keterlambatan' => true,
                'toleransi_terlambat' => 15,
                'berlaku_pulang_cepat' => true,
                'toleransi_pulang_cepat' => 15,
                'wajib_foto' => true,
                'wajib_isi_rencana_kegiatan' => false,
                'wajib_isi_realisasi_kegiatan' => false,
                'wajib_presensi_dilokasi' => true
            ];

            DB::beginTransaction();

            $existingSetting = SimpegSettingKehadiran::active()->first();

            if ($existingSetting) {
                $oldData = $existingSetting->getOriginal();
                $existingSetting->update($defaultData);
                ActivityLogger::log('update', $existingSetting, $oldData);
                $setting = $existingSetting->fresh();
                $message = 'Setting kehadiran berhasil direset ke nilai default';
            } else {
                $setting = SimpegSettingKehadiran::create($defaultData);
                ActivityLogger::log('create', $setting, $setting->toArray());
                $message = 'Setting kehadiran default berhasil dibuat';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatSettingResponse($setting),
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereset setting: ' . $e->getMessage()
            ], 500);
        }
    }

    // Test coordinates (check if coordinates are within radius)
    public function testCoordinates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $setting = SimpegSettingKehadiran::active()->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting kehadiran belum dikonfigurasi'
                ], 404);
            }

            $testLat = $request->latitude;
            $testLon = $request->longitude;
            
            $distance = $setting->calculateDistance($testLat, $testLon);
            $isWithinRadius = $setting->isWithinRadius($testLat, $testLon);
            $maxRadius = $setting->radius ?? 100;

            return response()->json([
                'success' => true,
                'test_result' => [
                    'coordinates' => [
                        'latitude' => $testLat,
                        'longitude' => $testLon
                    ],
                    'distance_from_center' => round($distance, 2),
                    'max_radius' => $maxRadius,
                    'is_within_radius' => $isWithinRadius,
                    'distance_status' => $isWithinRadius ? 'DALAM RADIUS' : 'DILUAR RADIUS',
                    'maps_url' => "https://maps.google.com/?q={$testLat},{$testLon}"
                ],
                'center_location' => [
                    'nama_gedung' => $setting->nama_gedung,
                    'latitude' => $setting->latitude,
                    'longitude' => $setting->longitude,
                    'maps_url' => $setting->maps_url
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek koordinat: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get system info for attendance
    public function getSystemInfo()
    {
        try {
            $setting = SimpegSettingKehadiran::active()->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting kehadiran belum dikonfigurasi',
                    'configured' => false
                ], 404);
            }

            return response()->json([
                'success' => true,
                'configured' => true,
                'system_info' => [
                    'attendance_rules' => $setting->getLocationInfo()['rules'],
                    'tolerance_settings' => $setting->getLocationInfo()['toleransi'],
                    'location_info' => [
                        'nama_gedung' => $setting->nama_gedung,
                        'coordinates' => $setting->getLocationInfo()['koordinat'],
                        'radius_meter' => $setting->radius,
                        'maps_url' => $setting->maps_url
                    ]
                ],
                'validation_functions' => [
                    'can_check_late' => $setting->berlaku_keterlambatan,
                    'can_check_early_leave' => $setting->berlaku_pulang_cepat,
                    'location_validation_required' => $setting->wajib_presensi_dilokasi,
                    'photo_required' => $setting->wajib_foto,
                    'activity_plan_required' => $setting->wajib_isi_rencana_kegiatan,
                    'activity_report_required' => $setting->wajib_isi_realisasi_kegiatan
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil info sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper: Format setting response
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

    // Helper: Get form template
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

    // Helper: Get validation rules
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
            'wajib_presensi_dilokasi' => 'boolean'
        ];
    }
}