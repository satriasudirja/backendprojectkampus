<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Services\QrPinBundleGenerator;
use Carbon\Carbon;

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
        'qr_code_enabled',
        'qr_pin_code',           // ADDED
        'qr_pin_expires_at',     // ADDED
        'qr_pin_enabled'         // ADDED
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
        'qr_pin_enabled' => 'boolean',          // ADDED
        'qr_code_generated_at' => 'datetime',
        'qr_pin_expires_at' => 'datetime',      // ADDED
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Only generate bundle if QR or PIN is enabled
            if ($model->qr_code_enabled || $model->qr_pin_enabled) {
                $model->generateQrPinBundle();
            }
        });

        static::deleting(function ($model) {
            $model->deleteBundleFile();
        });
    }

    public function absensiRecords()
    {
        return $this->hasMany(SimpegAbsensiRecord::class, 'setting_kehadiran_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public static function getDefault()
    {
        return self::active()->first();
    }

    /**
     * Generate QR + PIN Bundle (Main Method)
     */
    public function generateQrPinBundle()
    {
        $generator = new QrPinBundleGenerator();
        $result = $generator->generateBundle($this);
        
        if ($result['success']) {
            $this->qr_code_path = $result['path'];
            $this->qr_code_generated_at = now();
            return true;
        }
        
        return false;
    }

    /**
     * Regenerate QR + PIN Bundle
     */
    public function regenerateQrPinBundle()
    {
        $generator = new QrPinBundleGenerator();
        $result = $generator->regenerateBundle($this);
        
        return $result['success'];
    }

    /**
     * Legacy method - now uses bundle generator
     */
    public function generateQrCode()
    {
        return $this->generateQrPinBundle();
    }

    /**
     * Legacy method - now uses bundle generator
     */
    public function regenerateQrCode()
    {
        return $this->regenerateQrPinBundle() && $this->save();
    }

    /**
     * Regenerate PIN only (keep same QR)
     */
    public function regeneratePinCode()
    {
        $generator = new QrPinBundleGenerator();
        
        // Just regenerate PIN, not full bundle
        do {
            $pin = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $exists = self::where('qr_pin_code', $pin)
                ->where('id', '!=', $this->id)
                ->exists();
        } while ($exists);
        
        $this->qr_pin_code = $pin;
        
        return $this->save();
    }

    public function deleteBundleFile()
    {
        if ($this->qr_code_path && Storage::disk('public')->exists($this->qr_code_path)) {
            Storage::disk('public')->delete($this->qr_code_path);
        }
    }

    public function deleteQrCodeFile()
    {
        return $this->deleteBundleFile();
    }

    public function getQrCodeUrl()
    {
        if (!$this->qr_code_path) {
            return null;
        }
        
        return Storage::disk('public')->url($this->qr_code_path);
    }

    public function getBundleDownloadUrl()
    {
        if (!$this->qr_code_path) {
            return null;
        }
        
        return route('api.setting-kehadiran.download-qr', $this->id);
    }

    public function validateQrToken($token)
    {
        if (!$this->qr_code_enabled) {
            return false;
        }

        return $this->qr_code_token === $token;
    }

    /**
     * Validate PIN code
     */
    public function validatePinCode($pin)
    {
        if (!$this->qr_pin_enabled) {
            return false;
        }

        if ($this->qr_pin_code !== $pin) {
            return false;
        }

        if ($this->qr_pin_expires_at && now()->isAfter($this->qr_pin_expires_at)) {
            \Log::warning('PIN code expired: ' . $pin);
            return false;
        }

        return true;
    }

    /**
     * Check if PIN is expired
     */
    public function isPinExpired()
    {
        if (!$this->qr_pin_expires_at) {
            return false;
        }

        return now()->isAfter($this->qr_pin_expires_at);
    }

    public function isWithinRadius($latitude, $longitude)
    {
        if (!$this->wajib_presensi_dilokasi) {
            return true;
        }

        $distance = $this->calculateDistance($latitude, $longitude);
        $maxRadius = $this->radius ?? 100;

        return $distance <= $maxRadius;
    }

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

    /**
     * Get complete bundle information
     */
    public function getBundleInfo()
    {
        $generator = new QrPinBundleGenerator();
        return $generator->getBundleInfo($this);
    }

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
            'qr_pin_bundle' => [
                'enabled' => $this->qr_code_enabled || $this->qr_pin_enabled,
                'bundle_url' => $this->getQrCodeUrl(),
                'download_url' => $this->getBundleDownloadUrl(),
                'qr_enabled' => $this->qr_code_enabled,
                'pin_enabled' => $this->qr_pin_enabled,
                'pin_code' => $this->qr_pin_enabled ? $this->qr_pin_code : null,
                'generated_at' => $this->qr_code_generated_at?->format('Y-m-d H:i:s'),
                'pin_expired' => $this->isPinExpired()
            ],
            'rules' => [
                'wajib_foto' => $this->wajib_foto ?? true,
                'wajib_dilokasi' => $this->wajib_presensi_dilokasi ?? true,
                'wajib_rencana_kegiatan' => $this->wajib_isi_rencana_kegiatan ?? false,
                'wajib_realisasi_kegiatan' => $this->wajib_isi_realisasi_kegiatan ?? false
            ],
            'toleransi' => [
                'terlambat_menit' => $this->berlaku_keterlambatan ? ($this->toleransi_terlambat ?? 15) : 0,
                'pulang_awal_menit' => $this->berlaku_pulang_cepat ? ($this->toleransi_pulang_cepat ?? 15) : 0
            ]
        ];
    }

    public function validateAttendance($data)
    {
        $errors = [];

        if ($this->qr_code_enabled || $this->qr_pin_enabled) {
            if (empty($data['qr_token']) && empty($data['pin_code'])) {
                $errors[] = 'QR Code atau PIN diperlukan';
            } elseif (!empty($data['qr_token'])) {
                if (!$this->validateQrToken($data['qr_token'])) {
                    $errors[] = 'QR Code tidak valid';
                }
            } elseif (!empty($data['pin_code'])) {
                if (!$this->validatePinCode($data['pin_code'])) {
                    $errors[] = 'PIN tidak valid atau sudah kadaluarsa';
                }
            }
        }

        if ($this->wajib_foto && empty($data['foto'])) {
            $errors[] = 'Foto wajib diupload';
        }

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

        if ($this->wajib_isi_rencana_kegiatan && empty($data['rencana_kegiatan'])) {
            $errors[] = 'Rencana kegiatan wajib diisi';
        }

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

    public function getMapsUrlAttribute()
    {
        return "https://maps.google.com/?q={$this->latitude},{$this->longitude}";
    }

    public function toSettingArray()
    {
        return [
            'id' => $this->id,
            'nama_gedung' => $this->nama_gedung,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius' => $this->radius ?? 100,
            'qr_pin_bundle' => [
                'enabled' => $this->qr_code_enabled || $this->qr_pin_enabled,
                'bundle_url' => $this->getQrCodeUrl(),
                'download_url' => $this->getBundleDownloadUrl(),
                'qr_enabled' => $this->qr_code_enabled,
                'pin_enabled' => $this->qr_pin_enabled,
                'pin_code' => $this->qr_pin_code,
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
                'wajib_presensi_dilokasi' => $this->wajib_presensi_dilokasi ?? true
            ],
            'maps_url' => $this->maps_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}