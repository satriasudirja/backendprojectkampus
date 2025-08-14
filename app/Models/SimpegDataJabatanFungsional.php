<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder; // Import Builder for type-hinting scopes
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB; // For DB::raw in scopes

class SimpegDataJabatanFungsional extends Model
{
    use HasUuids;
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_jabatan_fungsional';

    protected $primaryKey = 'id';

    protected $fillable = [
        'jabatan_fungsional_id',
        'pegawai_id',
        'tmt_jabatan',
        'pejabat_penetap',
        'no_sk',
        'tanggal_sk',
        'file_sk_jabatan',
        'tgl_input',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        // Jika ada kolom untuk alasan penolakan, bisa ditambahkan di sini, contoh:
        // 'alasan_penolakan',
    ];

    protected $casts = [
        'tmt_jabatan' => 'date',
        'tanggal_sk' => 'date',
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

    // Relationship to Pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relationship to Jabatan Fungsional
    public function jabatanFungsional()
    {
        return $this->belongsTo(SimpegJabatanFungsional::class, 'jabatan_fungsional_id');
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

    public function scopeFilterByJabatanFungsionalId(Builder $query, $jabatanFungsionalId)
    {
        if ($jabatanFungsionalId && $jabatanFungsionalId !== 'semua') {
            return $query->where('jabatan_fungsional_id', $jabatanFungsionalId);
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

    public function scopeFilterByTanggalSk(Builder $query, $tanggalSk)
    {
        if ($tanggalSk) {
            return $query->whereDate('tanggal_sk', $tanggalSk);
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

    // Methods for status changes (similar to previous implementations)
    public function approve()
    {
        if (in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DIAJUKAN, self::STATUS_DITOLAK])) {
            $this->update([
                'status_pengajuan' => self::STATUS_DISETUJUI,
                'tgl_disetujui' => now(),
                'tgl_ditolak' => null,
                // 'alasan_penolakan' => null, // Uncomment if you have this field
            ]);
            return true;
        }
        return false;
    }

    public function reject($reason = null)
    {
        if (in_array($this->status_pengajuan, [self::STATUS_DRAFT, self::STATUS_DIAJUKAN, self::STATUS_DISETUJUI])) {
            $this->update([
                'status_pengajuan' => self::STATUS_DITOLAK,
                'tgl_ditolak' => now(),
                'tgl_diajukan' => null,
                'tgl_disetujui' => null,
                // 'alasan_penolakan' => $reason, // Uncomment if you have this field
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
                // 'alasan_penolakan' => null, // Uncomment if you have this field
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