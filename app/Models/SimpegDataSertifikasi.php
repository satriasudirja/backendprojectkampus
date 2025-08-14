<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataSertifikasi extends Model
{
    use HasUuids;
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_sertifikasi';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_sertifikasi_id',
        'bidang_ilmu_id',
        'no_sertifikasi',
        'tgl_sertifikasi',
        'no_registrasi',
        'no_peserta',
        'peran',
        'penyelenggara',
        'tempat',
        'lingkup',
        'tgl_input',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'keterangan'
    ];

    protected $casts = [
        'tgl_sertifikasi' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime', 
        'tgl_ditolak' => 'datetime'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'tgl_sertifikasi',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis sertifikasi
    public function jenisSertifikasi()
    {
        return $this->belongsTo(SimpegMasterJenisSertifikasi::class, 'jenis_sertifikasi_id');
    }

    // Relasi ke bidang ilmu
    public function bidangIlmu()
    {
        return $this->belongsTo(RumpunBidangIlmu::class, 'bidang_ilmu_id');
    }

    // Relasi ke dokumen pendukung (polymorphic)
    public function dokumenPendukung()
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }

    // Scope untuk filter berdasarkan status pengajuan
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    // Scope untuk filter berdasarkan pegawai
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    // Scope untuk filter berdasarkan jenis sertifikasi
    public function scopeByJenisSertifikasi($query, $jenisId)
    {
        return $query->where('jenis_sertifikasi_id', $jenisId);
    }

    // Scope untuk filter berdasarkan bidang ilmu
    public function scopeByBidangIlmu($query, $bidangId)
    {
        return $query->where('bidang_ilmu_id', $bidangId);
    }

    // Scope untuk filter berdasarkan lingkup
    public function scopeByLingkup($query, $lingkup)
    {
        return $query->where('lingkup', $lingkup);
    }

    // Scope untuk search
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('no_sertifikasi', 'like', '%'.$term.'%')
              ->orWhere('no_registrasi', 'like', '%'.$term.'%')
              ->orWhere('no_peserta', 'like', '%'.$term.'%')
              ->orWhere('peran', 'like', '%'.$term.'%')
              ->orWhere('penyelenggara', 'like', '%'.$term.'%')
              ->orWhere('tempat', 'like', '%'.$term.'%')
              ->orWhereHas('jenisSertifikasi', function($q) use ($term) {
                  $q->where('nama_jenis_sertifikasi', 'like', '%'.$term.'%');
              })
              ->orWhereHas('bidangIlmu', function($q) use ($term) {
                  $q->where('nama_bidang_ilmu', 'like', '%'.$term.'%');
              });
        });
    }

    // Accessor untuk mendapatkan status label
    public function getStatusLabelAttribute()
    {
        $statusMap = [
            'draft' => 'Draft',
            'diajukan' => 'Diajukan',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak'
        ];

        return $statusMap[$this->status_pengajuan] ?? ucfirst($this->status_pengajuan);
    }

    // Accessor untuk mendapatkan status color
    public function getStatusColorAttribute()
    {
        $colorMap = [
            'draft' => 'secondary',
            'diajukan' => 'info',
            'disetujui' => 'success',
            'ditolak' => 'danger'
        ];

        return $colorMap[$this->status_pengajuan] ?? 'secondary';
    }

    // Mutator untuk set tgl_input otomatis jika kosong
    public function setTglInputAttribute($value)
    {
        $this->attributes['tgl_input'] = $value ?: now()->toDateString();
    }

    // Mutator untuk set status_pengajuan default
    public function setStatusPengajuanAttribute($value)
    {
        $this->attributes['status_pengajuan'] = $value ?: 'draft';
    }

    // Check apakah bisa diedit
    public function canEdit()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }

    // Check apakah bisa disubmit
    public function canSubmit()
    {
        return $this->status_pengajuan === 'draft';
    }

    // Check apakah bisa dihapus
    public function canDelete()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }

    // Boot method untuk set default values
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->status_pengajuan)) {
                $model->status_pengajuan = 'draft';
            }
            if (empty($model->tgl_input)) {
                $model->tgl_input = now()->toDateString();
            }
        });
    }
}