<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataJabatanAkademik extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_jabatan_akademik';

    protected $fillable = [
        'pegawai_id',
        'jabatan_akademik_id',
        'tmt_jabatan',
        'no_sk',
        'tgl_sk',
        'pejabat_penetap',
        'file_jabatan',
        'tgl_input',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
    ];

    protected $casts = [
        'tmt_jabatan' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime'
    ];

    // Status pengajuan constants
    const STATUS_DRAFT = 'draft';
    const STATUS_DIAJUKAN = 'diajukan';
    const STATUS_DISETUJUI = 'disetujui';
    const STATUS_DITOLAK = 'ditolak';

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke tabel jabatan akademik
    public function jabatanAkademik()
    {
        return $this->belongsTo(SimpegJabatanAkademik::class, 'jabatan_akademik_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status_pengajuan', self::STATUS_DRAFT);
    }

    public function scopeDiajukan($query)
    {
        return $query->where('status_pengajuan', self::STATUS_DIAJUKAN);
    }

    public function scopeDisetujui($query)
    {
        return $query->where('status_pengajuan', self::STATUS_DISETUJUI);
    }

    public function scopeDitolak($query)
    {
        return $query->where('status_pengajuan', self::STATUS_DITOLAK);
    }

    // Accessor untuk status yang dapat diedit
    public function getCanEditAttribute()
    {
        return in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DITOLAK]);
    }

    // Accessor untuk status yang dapat diajukan
    public function getCanSubmitAttribute()
    {
        return $this->status_pengajuan === self::STATUS_DRAFT;
    }

    // Accessor untuk status yang dapat dihapus
    public function getCanDeleteAttribute()
    {
        return in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DITOLAK]);
    }

    // Method untuk mengubah status
    public function submitDraft()
    {
        if ($this->status_pengajuan === self::STATUS_DRAFT) {
            $this->update([
                'status_pengajuan' => self::STATUS_DIAJUKAN,
                'tgl_diajukan' => now()
            ]);
            return true;
        }
        return false;
    }

    public function approve()
    {
        if ($this->status_pengajuan === self::STATUS_DIAJUKAN) {
            $this->update([
                'status_pengajuan' => self::STATUS_DISETUJUI,
                'tgl_disetujui' => now()
            ]);
            return true;
        }
        return false;
    }

    public function reject($reason = null)
    {
        if ($this->status_pengajuan === self::STATUS_DIAJUKAN) {
            $this->update([
                'status_pengajuan' => self::STATUS_DITOLAK,
                'tgl_ditolak' => now(),
                'alasan_penolakan' => $reason
            ]);
            return true;
        }
        return false;
    }

    // Boot method untuk set default values
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Set default status jika tidak ada
            if (empty($model->status_pengajuan)) {
                $model->status_pengajuan = self::STATUS_DRAFT;
            }
            
            // Set tgl_input jika tidak ada
            if (empty($model->tgl_input)) {
                $model->tgl_input = now()->toDateString();
            }
        });
    }
}