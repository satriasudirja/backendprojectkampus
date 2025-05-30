<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SimpegDataOrganisasi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_organisasi';

    protected $fillable = [
        'pegawai_id',
        'nama_organisasi',
        'jabatan_dalam_organisasi',
        'jenis_organisasi',
        'tempat_organisasi',
        'periode_mulai',
        'periode_selesai',
        'website',
        'keterangan',
        'file_dokumen',
        'status_pengajuan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'keterangan_penolakan'
    ];

    protected $dates = [
        'periode_mulai',
        'periode_selesai', 
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime'
    ];

    // Default values
    protected $attributes = [
        'status_pengajuan' => 'draft',
        'jenis_organisasi' => 'lainnya'
    ];

    // Relasi dengan pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id', 'id');
    }

    // Scope untuk filter berdasarkan status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    // Scope untuk filter berdasarkan jenis organisasi
    public function scopeByJenisOrganisasi($query, $jenis)
    {
        return $query->where('jenis_organisasi', $jenis);
    }

    // Scope untuk filter berdasarkan pegawai
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    // Scope untuk filter berdasarkan periode
    public function scopeByPeriode($query, $mulai = null, $selesai = null)
    {
        if ($mulai) {
            $query->where('periode_mulai', '>=', $mulai);
        }
        
        if ($selesai) {
            $query->where(function($q) use ($selesai) {
                $q->where('periode_selesai', '<=', $selesai)
                  ->orWhereNull('periode_selesai');
            });
        }
        
        return $query;
    }

    // Scope untuk data yang masih aktif (belum berakhir atau belum ada tanggal selesai)
    public function scopeAktif($query)
    {
        return $query->where(function($q) {
            $q->whereNull('periode_selesai')
              ->orWhere('periode_selesai', '>=', Carbon::now()->toDateString());
        });
    }

    // Scope untuk pencarian
    public function scopeSearch($query, $search)
    {
        if (!$search) return $query;
        
        return $query->where(function($q) use ($search) {
            $q->where('nama_organisasi', 'like', '%'.$search.'%')
              ->orWhere('jabatan_dalam_organisasi', 'like', '%'.$search.'%')
              ->orWhere('tempat_organisasi', 'like', '%'.$search.'%')
              ->orWhere('jenis_organisasi', 'like', '%'.$search.'%')
              ->orWhere('keterangan', 'like', '%'.$search.'%')
              ->orWhere('website', 'like', '%'.$search.'%');
        });
    }

    // Accessor untuk status pengajuan dengan default
    public function getStatusPengajuanAttribute($value)
    {
        return $value ?? 'draft';
    }

    // Accessor untuk nama jenis organisasi yang lebih readable
    public function getJenisOrganisasiLabelAttribute()
    {
        $labels = [
            'lokal' => 'Lokal',
            'nasional' => 'Nasional',
            'internasional' => 'Internasional',
            'lainnya' => 'Lainnya'
        ];
        
        return $labels[$this->jenis_organisasi] ?? ucfirst($this->jenis_organisasi);
    }

    // Accessor untuk periode yang sudah diformat
    public function getPeriodeFormattedAttribute()
    {
        $mulai = $this->periode_mulai ? Carbon::parse($this->periode_mulai)->format('d/m/Y') : '';
        $selesai = $this->periode_selesai ? Carbon::parse($this->periode_selesai)->format('d/m/Y') : 'Sekarang';
        
        return $mulai . ($mulai ? ' - ' . $selesai : '');
    }

    // Accessor untuk durasi organisasi dalam bulan
    public function getDurasiAttribute()
    {
        if (!$this->periode_mulai) return null;
        
        $mulai = Carbon::parse($this->periode_mulai);
        $selesai = $this->periode_selesai ? Carbon::parse($this->periode_selesai) : Carbon::now();
        
        return $mulai->diffInMonths($selesai);
    }

    // Accessor untuk URL website yang sudah diformat
    public function getWebsiteFormattedAttribute()
    {
        if (!$this->website) return null;
        
        // Add https:// if not present
        if (!str_starts_with($this->website, 'http://') && !str_starts_with($this->website, 'https://')) {
            return 'https://' . $this->website;
        }
        
        return $this->website;
    }

    // Mutator untuk format tanggal yang konsisten
    public function setPeriodeMulaiAttribute($value)
    {
        $this->attributes['periode_mulai'] = $value ? Carbon::parse($value)->toDateString() : null;
    }

    public function setPeriodeSelesaiAttribute($value)
    {
        $this->attributes['periode_selesai'] = $value ? Carbon::parse($value)->toDateString() : null;
    }

    // Mutator untuk website formatting
    public function setWebsiteAttribute($value)
    {
        if ($value && !str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
            $this->attributes['website'] = 'https://' . $value;
        } else {
            $this->attributes['website'] = $value;
        }
    }

    // Helper methods untuk status checking
    public function isDraft()
    {
        return $this->status_pengajuan === 'draft';
    }

    public function isDiajukan()
    {
        return $this->status_pengajuan === 'diajukan';
    }

    public function isDisetujui()
    {
        return $this->status_pengajuan === 'disetujui';
    }

    public function isDitolak()
    {
        return $this->status_pengajuan === 'ditolak';
    }

    public function canEdit()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }

    public function canDelete()
    {
        return in_array($this->status_pengajuan, ['draft', 'ditolak']);
    }

    public function canSubmit()
    {
        return $this->status_pengajuan === 'draft';
    }

    public function isAktif()
    {
        if (!$this->periode_selesai) return true;
        
        return Carbon::parse($this->periode_selesai)->gte(Carbon::now());
    }

    // Helper method untuk mendapatkan info status lengkap
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

        return $statusMap[$this->status_pengajuan] ?? [
            'label' => ucfirst($this->status_pengajuan),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }

    // Boot method untuk auto-setting default values dan timestamps
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->status_pengajuan) {
                $model->status_pengajuan = 'draft';
            }
            
            if (!$model->jenis_organisasi) {
                $model->jenis_organisasi = 'lainnya';
            }
            
            if (!$model->tgl_input) {
                $model->tgl_input = Carbon::now()->toDateString();
            }
        });

        static::updating(function ($model) {
            // Auto set timestamps based on status changes
            if ($model->isDirty('status_pengajuan')) {
                switch ($model->status_pengajuan) {
                    case 'diajukan':
                        if (!$model->tgl_diajukan) {
                            $model->tgl_diajukan = Carbon::now();
                        }
                        break;
                    case 'disetujui':
                        if (!$model->tgl_disetujui) {
                            $model->tgl_disetujui = Carbon::now();
                        }
                        break;
                    case 'ditolak':
                        if (!$model->tgl_ditolak) {
                            $model->tgl_ditolak = Carbon::now();
                        }
                        break;
                    case 'draft':
                        // Reset timestamps when back to draft
                        $model->tgl_diajukan = null;
                        $model->tgl_disetujui = null;
                        $model->tgl_ditolak = null;
                        $model->keterangan_penolakan = null;
                        break;
                }
            }
        });
    }
}