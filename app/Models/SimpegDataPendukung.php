<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SimpegDataPendukung extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'simpeg_data_pendukung';

    protected $fillable = [
        'tipe_dokumen',
        'file_path',
        'nama_dokumen',
        'jenis_dokumen_id',
        'keterangan',
        'pendukungable_type',
        'pendukungable_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Polymorphic relation to the owning model (pendukungable).
     */
    public function pendukungable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Accessor untuk mendapatkan URL file
     */
    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            // Sesuaikan path berdasarkan tipe dokumen atau model yang menggunakan
            $basePath = $this->getStorageBasePath();
            return url('storage/' . $basePath . '/' . $this->file_path);
        }
        return null;
    }

    /**
     * Accessor untuk cek apakah file exists
     */
    public function getFileExistsAttribute()
    {
        if ($this->file_path) {
            $basePath = $this->getStorageBasePath();
            return Storage::exists('public/' . $basePath . '/' . $this->file_path);
        }
        return false;
    }

    /**
     * Accessor untuk mendapatkan ukuran file
     */
    public function getFileSizeAttribute()
    {
        if ($this->file_path && $this->file_exists) {
            $basePath = $this->getStorageBasePath();
            return Storage::size('public/' . $basePath . '/' . $this->file_path);
        }
        return 0;
    }

    /**
     * Accessor untuk format ukuran file yang human readable
     */
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }

    /**
     * Accessor untuk mendapatkan ekstensi file
     */
    public function getFileExtensionAttribute()
    {
        if ($this->file_path) {
            return pathinfo($this->file_path, PATHINFO_EXTENSION);
        }
        return null;
    }

    /**
     * Helper method untuk mendapatkan base path storage berdasarkan model
     */
    private function getStorageBasePath()
    {
        switch ($this->pendukungable_type) {
            case 'App\Models\SimpegDataDiklat':
                return 'pegawai/diklat/dokumen';
            case 'App\Models\SimpegDataKeluargaPegawai':
                return 'pegawai/keluarga/dokumen';
            case 'App\Models\SimpegDataRiwayatPekerjaan':
                return 'pegawai/riwayat-pekerjaan';
            default:
                return 'pegawai/dokumen/lainnya';
        }
    }

    /**
     * Method untuk menghapus file fisik
     */
    public function deleteFile()
    {
        if ($this->file_path) {
            $basePath = $this->getStorageBasePath();
            Storage::delete('public/' . $basePath . '/' . $this->file_path);
        }
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Auto delete file ketika record dihapus
        static::deleting(function ($model) {
            $model->deleteFile();
        });
    }

    /**
     * Scope untuk filter by tipe dokumen
     */
    public function scopeByTipeDokumen($query, $tipe)
    {
        return $query->where('tipe_dokumen', $tipe);
    }

    /**
     * Scope untuk filter by jenis dokumen
     */
    public function scopeByJenisDokumen($query, $jenisId)
    {
        return $query->where('jenis_dokumen_id', $jenisId);
    }
}