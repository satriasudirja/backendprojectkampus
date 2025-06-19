<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Added for consistency if needed
use Illuminate\Database\Eloquent\Builder; // Import Builder for type-hinting scopes
use Illuminate\Support\Facades\DB; // For DB::raw in scopes


class SimpegDataPangkat extends Model
{
    use HasFactory;
    use SoftDeletes; // Added for consistency if you intend to soft delete these records

    protected $table = 'simpeg_data_pangkat';

    protected $fillable = [
        'pegawai_id',
        'jenis_sk_id',
        'jenis_kenaikan_pangkat_id',
        'pangkat_id',
        'tmt_pangkat',
        'no_sk',
        'tgl_sk',
        'pejabat_penetap',
        'masa_kerja_tahun',
        'masa_kerja_bulan',
        'acuan_masa_kerja',
        'file_pangkat',
        'tgl_input',
        'status_pengajuan',
        'is_aktif',
        // Tambahkan jika ada di DB dan ingin dikelola melalui fillable
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
    ];

    protected $casts = [
        'tmt_pangkat' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime', // Added for consistency with controller logic
        'tgl_disetujui' => 'datetime', // Added for consistency with controller logic
        'tgl_ditolak' => 'datetime', // Already present
        'acuan_masa_kerja' => 'boolean',
        'is_aktif' => 'boolean',
        'masa_kerja_tahun' => 'integer',
        'masa_kerja_bulan' => 'integer'
    ];

    // Konstanta untuk status pengajuan
    const STATUS_DRAFT = 'draft';
    const STATUS_DIAJUKAN = 'diajukan';
    const STATUS_DISETUJUI = 'disetujui';
    const STATUS_DITOLAK = 'ditolak';
    
    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis SK
    public function jenisSk()
    {
        return $this->belongsTo(SimpegDaftarJenisSk::class, 'jenis_sk_id');
    }

    // Relasi ke jenis kenaikan pangkat
    public function jenisKenaikanPangkat()
    {
        return $this->belongsTo(SimpegJenisKenaikanPangkat::class, 'jenis_kenaikan_pangkat_id');
    }

    // Relasi ke pangkat
    public function pangkat()
    {
        return $this->belongsTo(SimpegMasterPangkat::class, 'pangkat_id');
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

    public function scopeFilterByPangkatId(Builder $query, $pangkatId)
    {
        if ($pangkatId && $pangkatId !== 'semua') {
            return $query->where('pangkat_id', $pangkatId);
        }
        return $query;
    }

    public function scopeFilterByJenisSkId(Builder $query, $jenisSkId)
    {
        if ($jenisSkId && $jenisSkId !== 'semua') {
            return $query->where('jenis_sk_id', $jenisSkId);
        }
        return $query;
    }

    public function scopeFilterByJenisKenaikanPangkatId(Builder $query, $jenisKenaikanPangkatId)
    {
        if ($jenisKenaikanPangkatId && $jenisKenaikanPangkatId !== 'semua') {
            return $query->where('jenis_kenaikan_pangkat_id', $jenisKenaikanPangkatId);
        }
        return $query;
    }

    public function scopeFilterByTmtPangkat(Builder $query, $tmtPangkat)
    {
        if ($tmtPangkat) {
            return $query->whereDate('tmt_pangkat', $tmtPangkat);
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