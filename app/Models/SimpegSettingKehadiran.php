<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegSettingKehadiran extends Model
{
    use HasUuids;
    use SoftDeletes;

    // Nama tabel
    protected $table = 'simpeg_setting_kehadiran';

    // Primary key
    protected $primaryKey = 'id';

    // Kolom yang bisa diisi massal
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
        'wajib_presensi_dilokasi'
    ];

    // Casting tipe data
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

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

    // Method untuk validasi koordinat dalam radius
    public function isWithinRadius($latitude, $longitude)
    {
        if (!$this->wajib_presensi_dilokasi) {
            return true; // Tidak wajib di lokasi
        }

        $distance = $this->calculateDistance($latitude, $longitude);
        $maxRadius = $this->radius ?? 100; // Default 100 meter

        return $distance <= $maxRadius;
    }

    // Method untuk menghitung jarak dari koordinat ke lokasi setting
    public function calculateDistance($latitude, $longitude)
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $latDiff = deg2rad($latitude - $this->latitude);
        $lonDiff = deg2rad($longitude - $this->longitude);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Jarak dalam meter
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

    // Method untuk cek apakah terlambat
    public function isLate($currentTime)
    {
        if (!$this->berlaku_keterlambatan) {
            return false;
        }

        $standardTime = \Carbon\Carbon::createFromTime(8, 0, 0); // Default 08:00
        $toleranceMinutes = $this->toleransi_terlambat ?? 15;
        $limitTime = $standardTime->addMinutes($toleranceMinutes);

        return \Carbon\Carbon::parse($currentTime)->gt($limitTime);
    }

    // Method untuk cek apakah pulang awal
    public function isEarlyLeave($currentTime)
    {
        if (!$this->berlaku_pulang_cepat) {
            return false;
        }

        $standardTime = \Carbon\Carbon::createFromTime(16, 0, 0); // Default 16:00
        $toleranceMinutes = $this->toleransi_pulang_cepat ?? 15;
        $limitTime = $standardTime->subMinutes($toleranceMinutes);

        return \Carbon\Carbon::parse($currentTime)->lt($limitTime);
    }

    // Method untuk validasi absensi berdasarkan setting
    public function validateAttendance($data)
    {
        $errors = [];

        // Validasi foto wajib
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

    // Accessor untuk mendapatkan info lengkap
    public function getInfoLengkapAttribute()
    {
        return $this->getLocationInfo();
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