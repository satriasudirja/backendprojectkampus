<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SimpegAbsensiCorrection extends Model
{
    use HasUuids;
    
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