<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanCutiDosen extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'simpeg_pengajuan_cuti_dosen';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pegawai_id',
        'no_urut_cuti',
        'jenis_cuti',
        'tgl_mulai',
        'tgl_selesai',
        'jumlah_cuti',
        'alasan_cuti',
        'alamat_selama_cuti',
        'no_telp',
        'file_cuti',
        'status_pengajuan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'keterangan'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'date',
        'tgl_disetujui' => 'date',
        'tgl_ditolak' => 'date',
        'jumlah_cuti' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the pegawai that owns the cuti application.
     */
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
    
    /**
     * Scope a query to only include drafts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft($query)
    {
        return $query->where('status_pengajuan', 'draft');
    }
    
    /**
     * Scope a query to only include submitted applications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status_pengajuan', 'diajukan');
    }
    
    /**
     * Scope a query to only include approved applications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status_pengajuan', 'disetujui');
    }
    
    /**
     * Scope a query to only include rejected applications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('status_pengajuan', 'ditolak');
    }
    
    /**
     * Check if the application is editable.
     *
     * @return bool
     */
    public function isEditable()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }
    
    /**
     * Check if the application can be submitted.
     *
     * @return bool
     */
    public function canSubmit()
    {
        return $this->status_pengajuan === 'draft';
    }
    
    /**
     * Check if the application can be deleted.
     *
     * @return bool
     */
    public function canDelete()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }
    
    /**
     * Check if the application can be printed.
     *
     * @return bool
     */
    public function canPrint()
    {
        return $this->status_pengajuan === 'disetujui';
    }
}