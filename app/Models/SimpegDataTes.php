<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SimpegDataTes extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_tes';

    protected $primaryKey = 'id';
    
    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_tes_id',
        'nama_tes',
        'penyelenggara',
        'tgl_tes',
        'skor',
        'file_pendukung',
        'tgl_input',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
    
    ];

    protected $casts = [
        'tgl_tes' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime', 
        'tgl_ditolak' => 'datetime',
        'skor' => 'float'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'tgl_tes',
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

    // Relasi ke jenis tes
    public function jenisTes()
    {
        return $this->belongsTo(SimpegDaftarJenisTest::class, 'jenis_tes_id');
    }

    // Polymorphic relation untuk dokumen pendukung
    public function dokumenPendukung()
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }

    // Accessor untuk mendapatkan URL file pendukung
    public function getFilePendukungUrlAttribute()
    {
        if ($this->file_pendukung) {
            return url('storage/pegawai/tes/dokumen/' . $this->file_pendukung);
        }
        return null;
    }

    // Accessor untuk cek apakah file pendukung exists
    public function getFilePendukungExistsAttribute()
    {
        if ($this->file_pendukung) {
            return Storage::exists('public/pegawai/tes/dokumen/' . $this->file_pendukung);
        }
        return false;
    }

    // Accessor untuk mendapatkan status info
    public function getStatusInfoAttribute()
    {
        $statusMap = [
            'draft' => [
                'label' => 'Draft',
                'color' => 'secondary',
                'icon' => 'edit',
                'description' => 'Belum diajukan'
            ],
            'diajukan' => [
                'label' => 'Diajukan',
                'color' => 'info',
                'icon' => 'clock',
                'description' => 'Menunggu persetujuan'
            ],
            'disetujui' => [
                'label' => 'Disetujui',
                'color' => 'success',
                'icon' => 'check-circle',
                'description' => 'Telah disetujui'
            ],
            'ditolak' => [
                'label' => 'Ditolak',
                'color' => 'danger',
                'icon' => 'x-circle',
                'description' => 'Ditolak, dapat diedit ulang'
            ]
        ];

        $status = $this->status_pengajuan ?? 'draft';
        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }

    // Accessor untuk cek permission
    public function getCanEditAttribute()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }

    public function getCanSubmitAttribute()
    {
        return $this->status_pengajuan === 'draft';
    }

    public function getCanDeleteAttribute()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }

    // Scopes
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    public function scopeByJenisTes($query, $jenisTestId)
    {
        return $query->where('jenis_tes_id', $jenisTestId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status_pengajuan', 'draft');
    }

    public function scopeDisetujui($query)
    {
        return $query->where('status_pengajuan', 'disetujui');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nama_tes', 'like', '%'.$search.'%')
              ->orWhere('penyelenggara', 'like', '%'.$search.'%')
              ->orWhere('skor', 'like', '%'.$search.'%')
              ->orWhereHas('jenisTes', function($jq) use ($search) {
                  $jq->where('jenis_tes', 'like', '%'.$search.'%');
              });
        });
    }

    // Boot method untuk auto-set default values
    protected static function boot()
    {
        parent::boot();

        // Set default status_pengajuan saat creating
        static::creating(function ($model) {
            if (empty($model->status_pengajuan)) {
                $model->status_pengajuan = 'draft';
            }
            
            if (empty($model->tgl_input)) {
                $model->tgl_input = now()->toDateString();
            }
        });

        // Auto delete file ketika record dihapus
        static::deleting(function ($model) {
            if ($model->file_pendukung) {
                Storage::delete('public/pegawai/tes/dokumen/' . $model->file_pendukung);
            }
        });
    }
}