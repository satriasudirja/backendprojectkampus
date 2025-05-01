<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SimpegBerita extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_berita';

    protected $fillable = [
        'unit_kerja_id',
        'judul',
        'konten',
        'slug',
        'tgl_posting',
        'tgl_expired',
        'prioritas',
        'gambar_berita',
        'file_berita',
    ];

    protected $casts = [
        'tgl_posting' => 'date',
        'tgl_expired' => 'date',
        'prioritas' => 'boolean',
        'unit_kerja_id' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Cast unit_kerja_id to JSON when saving
        static::saving(function ($model) {
            if (is_array($model->unit_kerja_id)) {
                $model->unit_kerja_id = json_encode($model->unit_kerja_id);
            }
        });

        // Cast unit_kerja_id from JSON when retrieving
        static::retrieved(function ($model) {
            if (is_string($model->unit_kerja_id) && is_array(json_decode($model->unit_kerja_id, true))) {
                $model->unit_kerja_id = json_decode($model->unit_kerja_id, true);
            }
        });
    }

    /**
     * Relasi dengan Unit Kerja
     */
    public function unitKerja()
    {
        // Assuming there's a model for unit_kerja
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    /**
     * Relasi dengan Jabatan Akademik (Many-to-Many)
     */
    public function jabatanAkademik()
    {
        return $this->belongsToMany(
            SimpegJabatanAkademik::class,
            'simpeg_berita_jabatan_akademik',
            'berita_id',
            'jabatan_akademik_id'
        );
    }

    /**
     * Generate slug from title
     */


    /**
     * Set slug otomatis dari judul
     */
    public function setJudulAttribute($value)
    {
        $this->attributes['judul'] = $value;
        
        // Generate slug dari judul
        $slug = Str::slug($value);
        $originalSlug = $slug;
        $count = 1;
        
        // Pastikan slug unik
        while (static::where('slug', $slug)
                    ->where('id', '!=', $this->id ?? 0)
                    ->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }
        
        $this->attributes['slug'] = $slug;
    }

    /**
     * Relasi dengan Unit Kerja (jika diperlukan)
     * Catatan: Ini opsional karena unit_kerja_id disimpan sebagai JSON array
     */

    /**
     * Scope untuk filter berdasarkan jabatan akademik
     */
    public function scopeWithJabatanAkademik($query, $jabatanAkademikId)
    {
        return $query->whereHas('jabatanAkademik', function ($q) use ($jabatanAkademikId) {
            $q->where('jabatan_akademik_id', $jabatanAkademikId);
        });
    }

    /**
     * Scope untuk filter berdasarkan unit kerja (mencari dalam array JSON)
     */
    public function scopeWithUnitKerja($query, $unitKerjaId)
    {
        // PostgreSQL specific JSON querying
        return $query->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$unitKerjaId])]);
    }

}