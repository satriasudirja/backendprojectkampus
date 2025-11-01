<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class SimpegSettingKehadiran extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'simpeg_setting_kehadiran';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama_gedung',
        'latitude',
        'longitude',
        'radius',
        'berlaku_keterlambatan',
        'toleransi_terlambat',
        'berlaku_pulang_cepat',
        'toleransi_pulang_cepat',
        'wajib_foto',
        'wajib_isi_rencana_kegiatan',
        'wajib_isi_realisasi_kegiatan',
        'wajib_presensi_dilokasi',
        'qr_code_token',
        'qr_code_path',
        'qr_code_generated_at',
        'qr_code_enabled'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'float',
        'berlaku_keterlambatan' => 'boolean',
        'toleransi_terlambat' => 'integer',
        'berlaku_pulang_cepat' => 'boolean',
        'toleransi_pulang_cepat' => 'integer',
        'wajib_foto' => 'boolean',
        'wajib_isi_rencana_kegiatan' => 'boolean',
        'wajib_isi_realisasi_kegiatan' => 'boolean',
        'wajib_presensi_dilokasi' => 'boolean',
        'qr_code_enabled' => 'boolean',
        'qr_code_generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Event untuk auto-generate QR Code saat create/update
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->generateQrCode();
        });

        static::deleting(function ($model) {
            $model->deleteQrCodeFile();
        });
    }

    // Relasi ke model AbsensiRecord
    public function absensiRecords()
    {
        return $this->hasMany(SimpegAbsensiRecord::class, 'setting_kehadiran_id');
    }

    // Scope untuk setting aktif
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    // Method untuk mendapatkan setting default
    public static function getDefault()
    {
        return self::active()->first();
    }

    // Method untuk generate QR Code
    public function generateQrCode()
    {
        try {
            \Log::info('Starting QR Code generation for setting: ' . $this->id);
            
            // LANGSUNG GUNAKAN FALLBACK METHOD (Google Charts API)
            // Karena SimpleSoftwareIO butuh Imagick yang susah di Windows
            \Log::info('Using Google Charts API for QR generation (no extension required)');
            return $this->generateQrCodeFallback();
            
        } catch (\Exception $e) {
            \Log::error('Failed to generate QR Code: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    // Fallback method menggunakan Google Charts API
    private function generateQrCodeFallback()
    {
        try {
            \Log::info('Using Google Charts API fallback for QR generation');
            
            $this->qr_code_token = Str::random(32) . '_' . uniqid();
            
            $qrData = json_encode([
                'type' => 'attendance',
                'setting_id' => $this->id ?? 'pending',
                'token' => $this->qr_code_token,
                'timestamp' => now()->timestamp,
                'location' => [
                    'name' => $this->nama_gedung,
                    'lat' => $this->latitude,
                    'lng' => $this->longitude
                ]
            ]);

            // Use Google Charts API
            $size = '400x400';
            $qrUrl = 'https://chart.googleapis.com/chart?chs=' . $size . '&cht=qr&chl=' . urlencode($qrData) . '&choe=UTF-8';
            
            \Log::info('Fetching QR from Google API: ' . $qrUrl);
            $qrImage = @file_get_contents($qrUrl);
            
            if ($qrImage === false) {
                throw new \Exception('Failed to generate QR code from Google API');
            }

            // Ensure directory exists
            $directory = storage_path('app/public/qr_codes');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $fileName = 'qr_' . ($this->id ?? uniqid()) . '_' . time() . '.png';
            $path = 'qr_codes/' . $fileName;
            
            Storage::disk('public')->put($path, $qrImage);
            
            if (!Storage::disk('public')->exists($path)) {
                throw new \Exception('QR Code file was not saved via fallback');
            }
            
            $this->qr_code_path = $path;
            $this->qr_code_generated_at = now();

            \Log::info('Fallback QR generation successful');
            return true;
        } catch (\Exception $e) {
            \Log::error('Fallback QR generation also failed: ' . $e->getMessage());
            return false;
        }
    }

    // Method untuk regenerate QR Code
    public function regenerateQrCode()
    {
        // Delete old QR code file
        $this->deleteQrCodeFile();
        
        // Generate new QR code
        return $this->generateQrCode() && $this->save();
    }

    // Method untuk delete QR Code file
    public function deleteQrCodeFile()
    {
        if ($this->qr_code_path && Storage::disk('public')->exists($this->qr_code_path)) {
            Storage::disk('public')->delete($this->qr_code_path);
        }
    }

    // Method untuk mendapatkan URL QR Code
    public function getQrCodeUrl()
    {
        if (!$this->qr_code_path) {
            return null;
        }
        
        return Storage::disk('public')->url($this->qr_code_path);
    }

    // Method untuk validasi QR Code token
    public function validateQrToken($token)
    {
        if (!$this->qr_code_enabled) {
            return false;
        }

        return $this->qr_code_token === $token;
    }

    // Method untuk validasi koordinat dalam radius
    public function isWithinRadius($latitude, $longitude)
    {
        if (!$this->wajib_presensi_dilokasi) {
            return true;
        }

        $distance = $this->calculateDistance($latitude, $longitude);
        $maxRadius = $this->radius ?? 100;

        return $distance <= $maxRadius;
    }

    // Method untuk menghitung jarak dari koordinat ke lokasi setting
    public function calculateDistance($latitude, $longitude)
    {
        $earthRadius = 6371000;

        $latDiff = deg2rad($latitude - $this->latitude);
        $lonDiff = deg2rad($longitude - $this->longitude);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    // Method untuk mendapatkan info lokasi lengkap
    public function getLocationInfo()
    {
        return [
            'nama_gedung' => $this->nama_gedung,
            'koordinat' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude
            ],
            'radius_meter' => $this->radius ?? 100,
            'alamat_maps' => "https://maps.google.com/?q={$this->latitude},{$this->longitude}",
            'qr_code' => [
                'enabled' => $this->qr_code_enabled,
                'url' => $this->getQrCodeUrl(),
                'generated_at' => $this->qr_code_generated_at?->format('Y-m-d H:i:s')
            ],
            'rules' => [
                'wajib_foto' => $this->wajib_foto ?? true,
                'wajib_dilokasi' => $this->wajib_presensi_dilokasi ?? true,
                'wajib_rencana_kegiatan' => $this->wajib_isi_rencana_kegiatan ?? false,
                'wajib_realisasi_kegiatan' => $this->wajib_isi_realisasi_kegiatan ?? false,
                'qr_code_enabled' => $this->qr_code_enabled ?? false
            ],
            'toleransi' => [
                'terlambat_menit' => $this->berlaku_keterlambatan ? ($this->toleransi_terlambat ?? 15) : 0,
                'pulang_awal_menit' => $this->berlaku_pulang_cepat ? ($this->toleransi_pulang_cepat ?? 15) : 0
            ]
        ];
    }

    // Method untuk validasi absensi berdasarkan setting
    public function validateAttendance($data)
    {
        $errors = [];

        // Validasi QR Code jika enabled
        if ($this->qr_code_enabled) {
            if (empty($data['qr_token'])) {
                $errors[] = 'QR Code token diperlukan';
            } elseif (!$this->validateQrToken($data['qr_token'])) {
                $errors[] = 'QR Code tidak valid atau sudah kadaluarsa';
            }
        }

        // Validasi foto wajib (hanya jika QR tidak digunakan atau tetap wajib)
        if ($this->wajib_foto && empty($data['foto'])) {
            $errors[] = 'Foto wajib diupload';
        }

        // Validasi lokasi
        if ($this->wajib_presensi_dilokasi) {
            if (empty($data['latitude']) || empty($data['longitude'])) {
                $errors[] = 'Koordinat GPS diperlukan';
            } else {
                if (!$this->isWithinRadius($data['latitude'], $data['longitude'])) {
                    $distance = $this->calculateDistance($data['latitude'], $data['longitude']);
                    $maxRadius = $this->radius ?? 100;
                    $errors[] = "Anda berada di luar radius kerja. Jarak: " . round($distance, 2) . "m, Maksimal: {$maxRadius}m";
                }
            }
        }

        // Validasi rencana kegiatan
        if ($this->wajib_isi_rencana_kegiatan && empty($data['rencana_kegiatan'])) {
            $errors[] = 'Rencana kegiatan wajib diisi';
        }

        // Validasi realisasi kegiatan (untuk absen keluar)
        if (isset($data['type']) && $data['type'] === 'keluar') {
            if ($this->wajib_isi_realisasi_kegiatan && empty($data['realisasi_kegiatan'])) {
                $errors[] = 'Realisasi kegiatan wajib diisi';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // Accessor untuk maps URL
    public function getMapsUrlAttribute()
    {
        return "https://maps.google.com/?q={$this->latitude},{$this->longitude}";
    }

    // Method untuk export setting ke array
    public function toSettingArray()
    {
        return [
            'id' => $this->id,
            'nama_gedung' => $this->nama_gedung,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius' => $this->radius ?? 100,
            'qr_code' => [
                'enabled' => $this->qr_code_enabled,
                'url' => $this->getQrCodeUrl(),
                'token' => $this->qr_code_token,
                'generated_at' => $this->qr_code_generated_at
            ],
            'rules' => [
                'berlaku_keterlambatan' => $this->berlaku_keterlambatan ?? false,
                'toleransi_terlambat' => $this->toleransi_terlambat ?? 15,
                'berlaku_pulang_cepat' => $this->berlaku_pulang_cepat ?? false,
                'toleransi_pulang_cepat' => $this->toleransi_pulang_cepat ?? 15,
                'wajib_foto' => $this->wajib_foto ?? true,
                'wajib_isi_rencana_kegiatan' => $this->wajib_isi_rencana_kegiatan ?? false,
                'wajib_isi_realisasi_kegiatan' => $this->wajib_isi_realisasi_kegiatan ?? false,
                'wajib_presensi_dilokasi' => $this->wajib_presensi_dilokasi ?? true,
                'qr_code_enabled' => $this->qr_code_enabled ?? false
            ],
            'maps_url' => $this->maps_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}