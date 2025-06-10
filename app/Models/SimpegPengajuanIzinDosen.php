<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class SimpegPengajuanIzinDosen extends Model
{
    use SoftDeletes;
    
    protected $table = 'simpeg_izin_record';
    
    protected $fillable = [
        'pegawai_id',
        'jenis_izin_id',
        'no_izin',
        'tgl_mulai',
        'tgl_selesai',
        'jumlah_izin',
        'alasan_izin',
        'file_pendukung',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'approved_by',
        'keterangan'
    ];
    
    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'jumlah_izin' => 'integer',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime'
    ];
    
    /**
     * Boot the model.
     */
  
    
    /**
     * Get the pegawai that owns the izin record.
     */
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
    
    /**
     * Get the jenis izin that owns the izin record.
     */
    public function jenisIzin()
    {
        return $this->belongsTo(SimpegJenisIzin::class, 'jenis_izin_id');
    }
    
    /**
     * Get the approver (if any).
     */
    public function approver()
    {
        return $this->belongsTo(SimpegPegawai::class, 'approved_by');
    }
    
    /**
     * Scope a query to only include draft records.
     */
    public function scopeDraft($query)
    {
        return $query->where('status_pengajuan', 'draft');
    }
    
    /**
     * Scope a query to only include submitted records.
     */
    public function scopeDiajukan($query)
    {
        return $query->where('status_pengajuan', 'diajukan');
    }
    
    /**
     * Scope a query to only include approved records.
     */
    public function scopeDisetujui($query)
    {
        return $query->where('status_pengajuan', 'disetujui');
    }
    
    /**
     * Scope a query to only include rejected records.
     */
    public function scopeDitolak($query)
    {
        return $query->where('status_pengajuan', 'ditolak');
    }
    
    /**
     * Scope a query to only include records for current year.
     */
    public function scopeTahunIni($query)
    {
        return $query->whereYear('tgl_mulai', date('Y'));
    }
    
    /**
     * Get file path for the supporting document.
     */
    public function getFilePendukungPathAttribute()
    {
        if (!$this->file_pendukung) {
            return null;
        }
        
        return 'public/pegawai/izin/' . $this->file_pendukung;
    }
    
    /**
     * Get file URL for the supporting document.
     */
    public function getFilePendukungUrlAttribute()
    {
        if (!$this->file_pendukung) {
            return null;
        }
        
        return url('storage/pegawai/izin/' . $this->file_pendukung);
    }
    
    /**
     * Check if the record can be edited.
     */
    public function getCanEditAttribute()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }
    
    /**
     * Check if the record can be submitted.
     */
    public function getCanSubmitAttribute()
    {
        return $this->status_pengajuan === 'draft';
    }
    
    /**
     * Check if the record can be deleted.
     */
    public function getCanDeleteAttribute()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }
    
    /**
     * Check if the record can be printed.
     */
    public function getCanPrintAttribute()
    {
        return $this->status_pengajuan === 'disetujui';
    }
}