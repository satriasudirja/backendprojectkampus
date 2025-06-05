<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SimpegAbsensiRecord extends Model
{
    // Nama tabel
    protected $table = 'simpeg_absensi_record';

    // Primary key
    protected $primaryKey = 'id';
    
    // Kolom yang bisa diisi massal
    protected $fillable = [
        'pegawai_id',
        'setting_kehadiran_id',
        'jenis_kehadiran_id',
        'jam_kerja_id',
        'cuti_record_id',
        'izin_record_id',
        'tanggal_absensi',
        'jam_masuk',
        'jam_keluar',
        'terlambat',
        'pulang_awal',
        'check_sum_absensi',
        'deleted_at',
        
        // Field tambahan untuk GPS dan foto
        'lokasi_masuk',
        'lokasi_keluar',
        'latitude_masuk',
        'longitude_masuk',
        'latitude_keluar',
        'longitude_keluar',
        'foto_masuk',
        'foto_keluar',
        
        // Field untuk kegiatan
        'rencana_kegiatan',
        'realisasi_kegiatan',
        
        // Field untuk durasi
        'durasi_kerja',
        'durasi_terlambat',
        'durasi_pulang_awal',
        
        // Field untuk status
        'keterangan',
        'status_verifikasi',
        'verifikasi_oleh',
        'verifikasi_at'
    ];

    // Casting tipe data
    protected $casts = [
        'tanggal_absensi' => 'date',
        'jam_masuk' => 'datetime',
        'jam_keluar' => 'datetime',
        'terlambat' => 'boolean',
        'pulang_awal' => 'boolean',
        'latitude_masuk' => 'decimal:8',
        'longitude_masuk' => 'decimal:8',
        'latitude_keluar' => 'decimal:8',
        'longitude_keluar' => 'decimal:8',
        'durasi_kerja' => 'integer',
        'durasi_terlambat' => 'integer',
        'durasi_pulang_awal' => 'integer',
        'verifikasi_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relasi ke model Pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke model SettingKehadiran
    public function settingKehadiran()
    {
        return $this->belongsTo(SimpegSettingKehadiran::class, 'setting_kehadiran_id');
    }

    // Relasi ke model JenisKehadiran
    public function jenisKehadiran()
    {
        return $this->belongsTo(SimpegJenisKehadiran::class, 'jenis_kehadiran_id');
    }

    // Relasi ke model JamKerja
    public function jamKerja()
    {
        return $this->belongsTo(SimpegJamKerja::class, 'jam_kerja_id');
    }

    // Relasi ke model CutiRecord
    public function cutiRecord()
    {
        return $this->belongsTo(SimpegCutiRecord::class, 'cuti_record_id');
    }

    // Relasi ke model IzinRecord
    public function izinRecord()
    {
        return $this->belongsTo(SimpegIzinRecord::class, 'izin_record_id');
    }

    // Scope untuk filter berdasarkan status verifikasi
    public function scopeVerified($query)
    {
        return $query->where('status_verifikasi', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('status_verifikasi', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status_verifikasi', 'rejected');
    }

    // Scope untuk filter hari ini
    public function scopeToday($query)
    {
        return $query->where('tanggal_absensi', Carbon::today());
    }

    // Scope untuk filter bulan ini
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('tanggal_absensi', Carbon::now()->month)
                    ->whereYear('tanggal_absensi', Carbon::now()->year);
    }

    // Scope untuk filter tahun ini
    public function scopeThisYear($query)
    {
        return $query->whereYear('tanggal_absensi', Carbon::now()->year);
    }

    // Method untuk check apakah absensi bisa dikoreksi
    public function canBeCorreected()
    {
        // Business logic untuk menentukan apakah absensi bisa dikoreksi
        // Misalnya hanya bisa dikoreksi dalam 3 hari setelah tanggal absensi
        $maxCorrectionDays = 3;
        $correctionDeadline = $this->tanggal_absensi->addDays($maxCorrectionDays);
        
        return now() <= $correctionDeadline && 
               $this->status_verifikasi !== 'verified';
    }

    // Method untuk menghitung durasi kerja dalam menit
    public function calculateWorkingMinutes()
    {
        if (!$this->jam_masuk || !$this->jam_keluar) {
            return 0;
        }
        
        $jamMasuk = Carbon::parse($this->jam_masuk);
        $jamKeluar = Carbon::parse($this->jam_keluar);
        
        return $jamKeluar->diffInMinutes($jamMasuk);
    }

    // Method untuk format durasi kerja
    public function getFormattedWorkingDuration()
    {
        $minutes = $this->calculateWorkingMinutes();
        if ($minutes === 0) {
            return '-';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return sprintf('%d jam %d menit', $hours, $remainingMinutes);
    }

    // Method untuk check keterlambatan berdasarkan jam kerja
    public function isLate()
    {
        if (!$this->jam_masuk || !$this->jamKerja) {
            return false;
        }
        
        $jamMasukStandar = Carbon::parse($this->jamKerja->jam_masuk);
        $jamMasukRealisasi = Carbon::parse($this->jam_masuk);
        $toleransi = $this->jamKerja->toleransi_terlambat ?? 15; // default 15 menit
        
        $batasWaktu = $jamMasukStandar->addMinutes($toleransi);
        
        return $jamMasukRealisasi > $batasWaktu;
    }

    // Method untuk check pulang awal
    public function isEarlyLeave()
    {
        if (!$this->jam_keluar || !$this->jamKerja) {
            return false;
        }
        
        $jamKeluarStandar = Carbon::parse($this->jamKerja->jam_keluar);
        $jamKeluarRealisasi = Carbon::parse($this->jam_keluar);
        $toleransi = $this->jamKerja->toleransi_pulang_awal ?? 15; // default 15 menit
        
        $batasWaktu = $jamKeluarStandar->subMinutes($toleransi);
        
        return $jamKeluarRealisasi < $batasWaktu;
    }

    // Method untuk mendapatkan status kehadiran
    public function getAttendanceStatus()
    {
        if ($this->jam_masuk && $this->jam_keluar) {
            $status = 'hadir_lengkap';
            $label = 'Hadir Lengkap';
            $color = 'success';
            
            if ($this->terlambat && $this->pulang_awal) {
                $label = 'Hadir (Terlambat & Pulang Awal)';
                $color = 'warning';
            } elseif ($this->terlambat) {
                $label = 'Hadir (Terlambat)';
                $color = 'warning';
            } elseif ($this->pulang_awal) {
                $label = 'Hadir (Pulang Awal)';
                $color = 'warning';
            }
        } elseif ($this->jam_masuk) {
            $status = 'hadir_masuk';
            $label = 'Hadir Masuk';
            $color = 'info';
        } elseif ($this->cutiRecord) {
            $status = 'cuti';
            $label = 'Cuti';
            $color = 'primary';
        } elseif ($this->izinRecord) {
            $status = 'izin';
            $label = 'Izin';
            $color = 'secondary';
        } else {
            $status = 'alpa';
            $label = 'Alpha';
            $color = 'danger';
        }

        return [
            'status' => $status,
            'label' => $label,
            'color' => $color
        ];
    }

    // Method untuk mendapatkan jarak dari lokasi kerja
    public function getDistanceFromOffice($type = 'masuk')
    {
        if (!$this->settingKehadiran) {
            return null;
        }

        $lat = $type === 'masuk' ? $this->latitude_masuk : $this->latitude_keluar;
        $lng = $type === 'masuk' ? $this->longitude_masuk : $this->longitude_keluar;

        if (!$lat || !$lng) {
            return null;
        }

        return $this->calculateDistance(
            $lat,
            $lng,
            $this->settingKehadiran->latitude,
            $this->settingKehadiran->longitude
        );
    }

    // Method untuk menghitung jarak menggunakan Haversine formula
    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
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

    // Method untuk mendapatkan info lengkap absensi
    public function getFullAttendanceInfo()
    {
        $jamMasuk = $this->jam_masuk ? Carbon::parse($this->jam_masuk) : null;
        $jamKeluar = $this->jam_keluar ? Carbon::parse($this->jam_keluar) : null;

        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal_absensi->format('Y-m-d'),
            'hari' => $this->tanggal_absensi->locale('id')->isoFormat('dddd'),
            'jam_masuk' => $jamMasuk ? $jamMasuk->format('H:i:s') : null,
            'jam_keluar' => $jamKeluar ? $jamKeluar->format('H:i:s') : null,
            'durasi_kerja' => $this->getFormattedWorkingDuration(),
            'lokasi' => [
                'masuk' => $this->lokasi_masuk,
                'keluar' => $this->lokasi_keluar
            ],
            'koordinat' => [
                'masuk' => [
                    'latitude' => $this->latitude_masuk,
                    'longitude' => $this->longitude_masuk
                ],
                'keluar' => [
                    'latitude' => $this->latitude_keluar,
                    'longitude' => $this->longitude_keluar
                ]
            ],
            'jarak_dari_kantor' => [
                'masuk' => $this->getDistanceFromOffice('masuk'),
                'keluar' => $this->getDistanceFromOffice('keluar')
            ],
            'foto' => [
                'masuk' => $this->foto_masuk ? url('storage/' . $this->foto_masuk) : null,
                'keluar' => $this->foto_keluar ? url('storage/' . $this->foto_keluar) : null
            ],
            'kegiatan' => [
                'rencana' => $this->rencana_kegiatan,
                'realisasi' => $this->realisasi_kegiatan
            ],
            'status' => [
                'terlambat' => $this->terlambat,
                'pulang_awal' => $this->pulang_awal,
                'durasi_terlambat' => $this->durasi_terlambat,
                'durasi_pulang_awal' => $this->durasi_pulang_awal,
                'verifikasi' => $this->status_verifikasi
            ],
            'attendance_status' => $this->getAttendanceStatus(),
            'keterangan' => $this->keterangan,
            'check_sum' => $this->check_sum_absensi
        ];
    }

    // Method untuk validasi absensi
    public function validateAttendance()
    {
        $errors = [];

        // Validasi koordinat masuk
        if (!$this->latitude_masuk || !$this->longitude_masuk) {
            $errors[] = 'Koordinat absen masuk tidak lengkap';
        }

        // Validasi foto masuk
        if (!$this->foto_masuk) {
            $errors[] = 'Foto absen masuk tidak ada';
        }

        // Validasi jika sudah absen keluar
        if ($this->jam_keluar) {
            if (!$this->latitude_keluar || !$this->longitude_keluar) {
                $errors[] = 'Koordinat absen keluar tidak lengkap';
            }
            if (!$this->foto_keluar) {
                $errors[] = 'Foto absen keluar tidak ada';
            }
        }

        // Validasi lokasi jika wajib
        if ($this->settingKehadiran && $this->settingKehadiran->wajib_presensi_dilokasi) {
            $jarakMasuk = $this->getDistanceFromOffice('masuk');
            $radiusMax = $this->settingKehadiran->radius ?? 100;
            
            if ($jarakMasuk && $jarakMasuk > $radiusMax) {
                $errors[] = "Jarak absen masuk ({$jarakMasuk}m) melebihi radius maksimal ({$radiusMax}m)";
            }

            if ($this->jam_keluar) {
                $jarakKeluar = $this->getDistanceFromOffice('keluar');
                if ($jarakKeluar && $jarakKeluar > $radiusMax) {
                    $errors[] = "Jarak absen keluar ({$jarakKeluar}m) melebihi radius maksimal ({$radiusMax}m)";
                }
            }
        }

        // Validasi kegiatan jika wajib
        if ($this->settingKehadiran && $this->settingKehadiran->wajib_isi_rencana_kegiatan && !$this->rencana_kegiatan) {
            $errors[] = 'Rencana kegiatan wajib diisi';
        }

        if ($this->settingKehadiran && $this->settingKehadiran->wajib_isi_realisasi_kegiatan && $this->jam_keluar && !$this->realisasi_kegiatan) {
            $errors[] = 'Realisasi kegiatan wajib diisi';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // Method untuk generate checksum
    public function generateChecksum()
    {
        $data = [
            $this->pegawai_id,
            $this->tanggal_absensi->format('Y-m-d'),
            $this->jam_masuk ? $this->jam_masuk->timestamp : '',
            $this->jam_keluar ? $this->jam_keluar->timestamp : '',
            $this->latitude_masuk,
            $this->longitude_masuk,
            $this->latitude_keluar,
            $this->longitude_keluar
        ];

        return md5(implode('|', $data));
    }

    // Method untuk verify checksum
    public function verifyChecksum()
    {
        return $this->check_sum_absensi === $this->generateChecksum();
    }

    // Accessor untuk mendapatkan URL foto masuk
    public function getFotoMasukUrlAttribute()
    {
        return $this->foto_masuk ? url('storage/' . $this->foto_masuk) : null;
    }

    // Accessor untuk mendapatkan URL foto keluar
    public function getFotoKeluarUrlAttribute()
    {
        return $this->foto_keluar ? url('storage/' . $this->foto_keluar) : null;
    }

    // Accessor untuk mendapatkan durasi kerja terformat
    public function getDurasiKerjaFormattedAttribute()
    {
        return $this->getFormattedWorkingDuration();
    }

    // Accessor untuk mendapatkan status kehadiran
    public function getStatusKehadiranAttribute()
    {
        return $this->getAttendanceStatus();
    }

    // Method untuk approve/reject absensi
    public function updateVerificationStatus($status, $verifikatorId = null, $catatan = null)
    {
        $validStatuses = ['pending', 'verified', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Status verifikasi tidak valid');
        }

        $this->update([
            'status_verifikasi' => $status,
            'verifikasi_oleh' => $verifikatorId,
            'verifikasi_at' => $status !== 'pending' ? now() : null,
            'keterangan' => $catatan ? (($this->keterangan ? $this->keterangan . ' | ' : '') . $catatan) : $this->keterangan
        ]);

        return $this;
    }
}