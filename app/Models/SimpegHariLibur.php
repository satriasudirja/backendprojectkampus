<?php

// File: app/Models/SimpegHariLibur.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegHariLibur extends Model
{
    use SoftDeletes;
    use HasUuids;
    
    protected $table = 'simpeg_hari_libur';
    
    protected $fillable = [
        'tanggal_libur',
        'nama_libur',
        'keterangan',
        'jenis_libur',
        'is_active',
        'tahun'
    ];
    
    protected $casts = [
        'tanggal_libur' => 'date',
        'is_active' => 'boolean',
        'tahun' => 'integer'
    ];
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeByYear($query, $year)
    {
        return $query->where('tahun', $year);
    }
    
    public function scopeByJenis($query, $jenis)
    {
        return $query->where('jenis_libur', $jenis);
    }
}

// File: app/Models/SimpegJamKerja.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJamKerja extends Model
{
    use SoftDeletes;
    
    protected $table = 'simpeg_jam_kerja';
    
    protected $fillable = [
        'nama_shift',
        'jam_masuk',
        'jam_keluar',
        'jam_istirahat_mulai',
        'jam_istirahat_selesai',
        'toleransi_terlambat',
        'toleransi_pulang_awal',
        'durasi_kerja_standar',
        'is_default',
        'is_active',
        'hari_kerja'
    ];
    
    protected $casts = [
        'jam_masuk' => 'datetime:H:i:s',
        'jam_keluar' => 'datetime:H:i:s',
        'jam_istirahat_mulai' => 'datetime:H:i:s',
        'jam_istirahat_selesai' => 'datetime:H:i:s',
        'toleransi_terlambat' => 'integer',
        'toleransi_pulang_awal' => 'integer',
        'durasi_kerja_standar' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'hari_kerja' => 'array'
    ];
    
    public function absensiRecords()
    {
        return $this->hasMany(SimpegAbsensiRecord::class, 'jam_kerja_id');
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

// File: app/Models/SimpegAbsensiCorrection.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegAbsensiCorrection extends Model
{
    protected $table = 'simpeg_absensi_correction';
    
    protected $fillable = [
        'absensi_record_id',
        'pegawai_id',
        'tanggal_koreksi',
        'jam_masuk_asli',
        'jam_keluar_asli',
        'jam_masuk_koreksi',
        'jam_keluar_koreksi',
        'alasan_koreksi',
        'bukti_pendukung',
        'status_koreksi',
        'approved_by',
        'approved_at',
        'catatan_approval'
    ];
    
    protected $casts = [
        'tanggal_koreksi' => 'date',
        'jam_masuk_asli' => 'datetime:H:i:s',
        'jam_keluar_asli' => 'datetime:H:i:s',
        'jam_masuk_koreksi' => 'datetime:H:i:s',
        'jam_keluar_koreksi' => 'datetime:H:i:s',
        'approved_at' => 'datetime'
    ];
    
    public function absensiRecord()
    {
        return $this->belongsTo(SimpegAbsensiRecord::class, 'absensi_record_id');
    }
    
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
    
    public function approvedBy()
    {
        return $this->belongsTo(SimpegPegawai::class, 'approved_by');
    }
    
    public function scopePending($query)
    {
        return $query->where('status_koreksi', 'pending');
    }
    
    public function scopeApproved($query)
    {
        return $query->where('status_koreksi', 'approved');
    }
    
    public function scopeRejected($query)
    {
        return $query->where('status_koreksi', 'rejected');
    }
}

// File: app/Models/SimpegAttendanceSummary.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegAttendanceSummary extends Model
{
    protected $table = 'simpeg_attendance_summary';
    
    protected $fillable = [
        'pegawai_id',
        'tahun',
        'bulan',
        'total_hari_kerja',
        'total_hadir',
        'total_terlambat',
        'total_pulang_awal',
        'total_sakit',
        'total_izin',
        'total_alpa',
        'total_cuti',
        'total_hadir_libur',
        'total_jam_kerja_realisasi',
        'total_jam_kerja_standar',
        'total_durasi_terlambat',
        'total_durasi_pulang_awal',
        'persentase_kehadiran',
        'last_calculated_at'
    ];
    
    protected $casts = [
        'tahun' => 'integer',
        'bulan' => 'integer',
        'total_hari_kerja' => 'integer',
        'total_hadir' => 'integer',
        'total_terlambat' => 'integer',
        'total_pulang_awal' => 'integer',
        'total_sakit' => 'integer',
        'total_izin' => 'integer',
        'total_alpa' => 'integer',
        'total_cuti' => 'integer',
        'total_hadir_libur' => 'integer',
        'total_jam_kerja_realisasi' => 'integer',
        'total_jam_kerja_standar' => 'integer',
        'total_durasi_terlambat' => 'integer',
        'total_durasi_pulang_awal' => 'integer',
        'persentase_kehadiran' => 'decimal:2',
        'last_calculated_at' => 'datetime'
    ];
    
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
    
    public function scopeByYear($query, $year)
    {
        return $query->where('tahun', $year);
    }
    
    public function scopeByMonth($query, $month)
    {
        return $query->where('bulan', $month);
    }
    
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }
}

// Update SimpegAbsensiRecord model with additional relationships
// Add these methods to the existing SimpegAbsensiRecord model:

/*
public function jamKerja()
{
    return $this->belongsTo(SimpegJamKerja::class, 'jam_kerja_id');
}

public function corrections()
{
    return $this->hasMany(SimpegAbsensiCorrection::class, 'absensi_record_id');
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

// Method untuk check apakah absensi bisa dikoreksi
public function canBeCorreected()
{
    // Business logic untuk menentukan apakah absensi bisa dikoreksi
    // Misalnya hanya bisa dikoreksi dalam 3 hari setelah tanggal absensi
    $maxCorrectionDays = 3;
    $correctionDeadline = $this->tanggal_absensi->addDays($maxCorrectionDays);
    
    return now() <= $correctionDeadline && 
           $this->status_verifikasi !== 'verified' &&
           !$this->corrections()->where('status_koreksi', 'pending')->exists();
}

// Method untuk menghitung durasi kerja
public function calculateWorkingHours()
{
    if (!$this->jam_masuk || !$this->jam_keluar) {
        return 0;
    }
    
    $jamMasuk = Carbon::parse($this->jam_masuk);
    $jamKeluar = Carbon::parse($this->jam_keluar);
    
    // Kurangi dengan jam istirahat jika ada
    $istirahatMulai = null;
    $istirahatSelesai = null;
    
    if ($this->jamKerja) {
        $istirahatMulai = $this->jamKerja->jam_istirahat_mulai;
        $istirahatSelesai = $this->jamKerja->jam_istirahat_selesai;
    }
    
    $totalMinutes = $jamKeluar->diffInMinutes($jamMasuk);
    
    // Kurangi durasi istirahat jika dalam rentang kerja
    if ($istirahatMulai && $istirahatSelesai) {
        $istirahatStart = Carbon::parse($istirahatMulai);
        $istirahatEnd = Carbon::parse($istirahatSelesai);
        
        if ($jamMasuk <= $istirahatStart && $jamKeluar >= $istirahatEnd) {
            $totalMinutes -= $istirahatEnd->diffInMinutes($istirahatStart);
        }
    }
    
    return $totalMinutes;
}

// Method untuk check keterlambatan
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
*/