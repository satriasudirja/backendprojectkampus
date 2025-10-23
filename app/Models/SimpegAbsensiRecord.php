<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SimpegAbsensiRecord extends Model
{
    use HasUuids;
    protected $table = 'simpeg_absensi_record';
    protected $primaryKey = 'id';

    protected $fillable = [
        'pegawai_id', 'setting_kehadiran_id', 'jenis_kehadiran_id', 'jam_kerja_id',
        'cuti_record_id', 'izin_record_id', 'tanggal_absensi', 'jam_masuk', 'jam_keluar',
        'check_sum_absensi', 'deleted_at', 'lokasi_masuk', 'lokasi_keluar',
        'latitude_masuk', 'longitude_masuk', 'latitude_keluar', 'longitude_keluar',
        'foto_masuk', 'foto_keluar', 'rencana_kegiatan', 'realisasi_kegiatan',
        'durasi_kerja', 'durasi_terlambat', 'durasi_pulang_awal', 'keterangan',
        'status_verifikasi', 'verifikasi_oleh', 'verifikasi_at',
        'terlambat', 'pulang_awal'
    ];

    protected $casts = [
        'tanggal_absensi' => 'date', 'jam_masuk' => 'datetime', 'jam_keluar' => 'datetime',
        'terlambat' => 'boolean', 'pulang_awal' => 'boolean', 'latitude_masuk' => 'decimal:8',
        'longitude_masuk' => 'decimal:8', 'latitude_keluar' => 'decimal:8', 'longitude_keluar' => 'decimal:8',
        'durasi_kerja' => 'integer', 'durasi_terlambat' => 'integer', 'durasi_pulang_awal' => 'integer',
        'verifikasi_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime',
    ];

    // --- RELATIONS ---
    public function pegawai() { return $this->belongsTo(SimpegPegawai::class, 'pegawai_id'); }
    public function settingKehadiran() { return $this->belongsTo(SimpegSettingKehadiran::class, 'setting_kehadiran_id'); }
    public function jenisKehadiran() { return $this->belongsTo(SimpegJenisKehadiran::class, 'jenis_kehadiran_id'); }
    public function jamKerja() { return $this->belongsTo(SimpegJamKerja::class, 'jam_kerja_id'); }
    public function cutiRecord() { return $this->belongsTo(SimpegCutiRecord::class, 'cuti_record_id'); }
    public function izinRecord() { return $this->belongsTo(SimpegIzinRecord::class, 'izin_record_id'); }

    // --- ACCESSORS & HELPER METHODS ---

    /**
     * LOGIKA BARU: Menentukan status kehadiran berdasarkan prioritas data.
     * Cuti/Izin -> Hadir -> Alpha.
     */
    public function getAttendanceStatus()
    {
        // Prioritas 1: Cek Cuti
        if ($this->cuti_record_id && $this->cutiRecord) {
            return ['label' => 'Cuti', 'color' => 'primary'];
        }
        
        // Prioritas 2: Cek Izin
        if ($this->izin_record_id && $this->izinRecord) {
            $isSakit = stripos($this->izinRecord->jenis_izin, 'sakit') !== false;
            return $isSakit 
                ? ['label' => 'Sakit', 'color' => 'warning'] 
                : ['label' => 'Izin', 'color' => 'info'];
        }

        // Prioritas 3: Cek Jenis Kehadiran (untuk data manual)
        if ($this->jenisKehadiran) {
             return ['label' => $this->jenisKehadiran->nama_jenis, 'color' => $this->jenisKehadiran->warna ?? 'secondary'];
        }
        
        // Prioritas 4: Cek jam masuk & keluar
        if ($this->jam_masuk && $this->jam_keluar) {
            return ['label' => 'Hadir Lengkap', 'color' => 'success'];
        }
        if ($this->jam_masuk) {
            return ['label' => 'Hadir (Belum Pulang)', 'color' => 'info'];
        }
        
        // Default: Alpha
        return ['label' => 'Alpha', 'color' => 'danger'];
    }

    public function scopeAlphaOnly($query)
    {
        return $query->whereNull('jam_masuk')
                    ->whereNull('cuti_record_id')
                    ->whereNull('izin_record_id');
    }

    /**
     * LOGIKA BARU: Format durasi kerja dari field 'durasi_kerja' (dalam menit).
     */
    public function getFormattedWorkingDuration()
    {
        $minutes = $this->durasi_kerja;
        if (is_null($minutes) || $minutes <= 0) {
            return '-';
        }
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        return sprintf('%d jam %d menit', $hours, $remainingMinutes);
    }
}
