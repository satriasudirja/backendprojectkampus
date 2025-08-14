<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SimpegAttendanceSummary extends Model
{
    use HasUuids;
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