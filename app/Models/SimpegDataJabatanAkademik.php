<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder; // Import Builder for type-hinting scopes
use Illuminate\Support\Facades\DB; // For DB::raw in scopes

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
        // 'tanggal_mulai', // <-- Dihapus
        // 'alasan_penolakan', // <-- Dihapus
    ];

    protected $casts = [
        'tmt_jabatan' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
        // 'tanggal_mulai' => 'datetime' // <-- Dihapus
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
    public function scopeDraft(Builder $query)
    {
        return $query->where('status_pengajuan', self::STATUS_DRAFT);
    }

    public function scopeDiajukan(Builder $query)
    {
        return $query->where('status_pengajuan', self::STATUS_DIAJUKAN);
    }

    public function scopeDisetujui(Builder $query)
    {
        return $query->where('status_pengajuan', self::STATUS_DISETUJUI);
    }

    public function scopeDitolak(Builder $query)
    {
        return $query->where('status_pengajuan', self::STATUS_DITOLAK);
    }

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

    public function scopeFilterByJabatanAkademikId(Builder $query, $jabatanAkademikId)
    {
        if ($jabatanAkademikId && $jabatanAkademikId !== 'semua') {
            return $query->where('jabatan_akademik_id', $jabatanAkademikId);
        }
        return $query;
    }

    public function scopeFilterByNipNamaPegawai(Builder $query, $search)
    {
        if ($search) {
            return $query->whereHas('pegawai', function ($q) use ($search) {
                $q->where('nip', 'ilike', '%' . $search . '%') // Use 'ilike' for case-insensitive search in PostgreSQL
                  ->orWhere('nama', 'ilike', '%' . $search . '%');
            });
        }
        return $query;
    }

    public function scopeFilterByTmtJabatan(Builder $query, $tmtJabatan)
    {
        if ($tmtJabatan) {
            return $query->whereDate('tmt_jabatan', $tmtJabatan);
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
        // Allow admin to approve from draft, diajukan, ditolak
        if (in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DIAJUKAN, self::STATUS_DITOLAK])) {
            $this->update([
                'status_pengajuan' => self::STATUS_DISETUJUI,
                'tgl_disetujui' => now(),
                'tgl_ditolak' => null, // Clear rejection timestamp
                // 'alasan_penolakan' => null, // <-- Dihapus
            ]);
            return true;
        }
        return false;
    }

    public function reject($reason = null) // $reason parameter now unused
    {
        // Allow admin to reject from draft, diajukan, disetujui
        if (in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DIAJUKAN, self::STATUS_DISETUJUI])) {
            $this->update([
                'status_pengajuan' => self::STATUS_DITOLAK,
                'tgl_ditolak' => now(),
                'tgl_diajukan' => null, // Clear submitted timestamp
                'tgl_disetujui' => null, // Clear approved timestamp
                // 'alasan_penolakan' => $reason // <-- Dihapus
            ]);
            return true;
        }
        return false;
    }

    // Method to set status to draft (admin action)
    public function toDraft()
    {
        if ($this->status_pengajuan !== self::STATUS_DRAFT) {
            $this->update([
                'status_pengajuan' => self::STATUS_DRAFT,
                'tgl_diajukan' => null,
                'tgl_disetujui' => null,
                'tgl_ditolak' => null,
                // 'alasan_penolakan' => null, // <-- Dihapus
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