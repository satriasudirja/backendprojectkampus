<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder; // Import Builder for type-hinting scopes
use Illuminate\Support\Facades\DB; // For DB::raw in scopes

class SimpegDataJabatanStruktural extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_jabatan_struktural';

    protected $primaryKey = 'id';

    protected $fillable = [
        'pegawai_id',
        'jabatan_struktural_id',
        'tgl_mulai',
        'tgl_selesai',
        'no_sk',
        'tgl_sk',
        'pejabat_penetap',
        'file_jabatan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'status_pengajuan',
        // 'deleted_at', // 'deleted_at' is handled by SoftDeletes trait, no need in fillable
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
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

    // Relasi ke tabel jabatan struktural
    public function jabatanStruktural()
    {
        return $this->belongsTo(SimpegJabatanStruktural::class, 'jabatan_struktural_id');
    }

    // Scopes for filtering
    public function scopeByStatus(Builder $query, $status)
    {
        if ($status && $status !== 'semua') {
            return $query->where('status_pengajuan', $status);
        }
        return $query;
    }

    public function scopeFilterByUnitKerja(Builder $query, $unitKerjaId)
    {
        if ($unitKerjaId && $unitKerjaId !== 'semua') {
            return $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }
        return $query;
    }

    public function scopeFilterByJabatanStrukturalId(Builder $query, $jabatanStrukturalId)
    {
        if ($jabatanStrukturalId && $jabatanStrukturalId !== 'semua') {
            return $query->where('jabatan_struktural_id', $jabatanStrukturalId);
        }
        return $query;
    }

    public function scopeFilterByNipNamaPegawai(Builder $query, $search)
    {
        if ($search) {
            return $query->whereHas('pegawai', function ($q) use ($search) {
                $q->where('nip', 'ilike', '%' . $search . '%')
                  ->orWhere('nama', 'ilike', '%' . $search . '%');
            });
        }
        return $query;
    }

    public function scopeFilterByTglMulai(Builder $query, $tglMulai)
    {
        if ($tglMulai) {
            return $query->whereDate('tgl_mulai', $tglMulai);
        }
        return $query;
    }

    public function scopeFilterByTglSelesai(Builder $query, $tglSelesai)
    {
        if ($tglSelesai) {
            return $query->whereDate('tgl_selesai', $tglSelesai);
        }
        return $query;
    }

    public function scopeFilterByNoSk(Builder $query, $noSk)
    {
        if ($noSk) {
            return $query->where('no_sk', 'ilike', '%' . $noSk . '%');
        }
        return $query;
    }

    public function scopeFilterByTglSk(Builder $query, $tglSk)
    {
        if ($tglSk) {
            return $query->whereDate('tgl_sk', $tglSk);
        }
        return $query;
    }

    public function scopeFilterByTglDisetujui(Builder $query, $tglDisetujui)
    {
        if ($tglDisetujui) {
            return $query->whereDate('tgl_disetujui', $tglDisetujui);
        }
        return $query;
    }

    // Methods for status changes (consistent logic)
    public function approve()
    {
        if (in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DIAJUKAN, self::STATUS_DITOLAK])) {
            $this->update([
                'status_pengajuan' => self::STATUS_DISETUJUI,
                'tgl_disetujui' => now(),
                'tgl_ditolak' => null,
            ]);
            return true;
        }
        return false;
    }

    public function reject($reason = null) // $reason parameter is kept for consistency but not used for storing
    {
        if (in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DIAJUKAN, self::STATUS_DISETUJUI])) {
            $this->update([
                'status_pengajuan' => self::STATUS_DITOLAK,
                'tgl_ditolak' => now(),
                'tgl_diajukan' => null,
                'tgl_disetujui' => null,
            ]);
            return true;
        }
        return false;
    }

    public function toDraft()
    {
        if ($this->status_pengajuan !== self::STATUS_DRAFT) {
            $this->update([
                'status_pengajuan' => self::STATUS_DRAFT,
                'tgl_diajukan' => null,
                'tgl_disetujui' => null,
                'tgl_ditolak' => null,
            ]);
            return true;
        }
        return false;
    }

    // Boot method for default values on creation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->status_pengajuan)) {
                $model->status_pengajuan = self::STATUS_DRAFT;
            }
            if (empty($model->tgl_input)) {
                $model->tgl_input = now()->toDateString();
            }
        });
    }
}