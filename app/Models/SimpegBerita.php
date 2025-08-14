<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SimpegBerita extends Model
{
    use HasUuids;
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
        'unit_kerja_id' => 'array', // Tetap di sini, tapi akan di-override manual encode saat saving
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     * Mengomentari manual cast di boot karena $casts harusnya sudah cukup
     * DAN kita akan melakukan json_encode manual di controller jika DB column TEXT.
     */
    // protected static function boot()
    // {
    //     parent::boot();

    //     static::saving(function ($model) {
    //         if (is_array($model->unit_kerja_id)) {
    //             $model->unit_kerja_id = json_encode($model->unit_kerja_id);
    //         }
    //     });

    //     static::retrieved(function ($model) {
    //         if (is_string($model->unit_kerja_id) && is_array(json_decode($model->unit_kerja_id, true))) {
    //             $model->unit_kerja_id = json_decode($model->unit_kerja_id, true);
    //         }
    //     });
    // }

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
     * Set slug otomatis dari judul
     * Ini adalah "mutator" yang akan dipanggil saat Anda mengatur $model->judul = '...'
     */
    public function setJudulAttribute($value)
    {
        $this->attributes['judul'] = $value;
        
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
     * Relasi dengan Unit Kerja (One-to-Many, jika unit_kerja_id hanya 1)
     * atau untuk mendapatkan detail dari unit kerja pertama di array.
     */
    public function unitKerja()
    {
        // Jika unit_kerja_id adalah array, ambil nilai pertama untuk relasi belongsTo
        $unitKerjaId = is_array($this->unit_kerja_id) && !empty($this->unit_kerja_id) 
            ? $this->unit_kerja_id[0] 
            : $this->unit_kerja_id;
        
        // Asumsi 'kode_unit' adalah kolom yang cocok di tabel simpeg_unit_kerja
        // Penting: Pastikan SimpegUnitKerja diimport jika ingin digunakan di sini
        return $this->belongsTo(\App\Models\SimpegUnitKerja::class, 'unit_kerja_id', 'kode_unit')
            ->withDefault([
                'nama_unit' => 'Tidak ditemukan'
            ]);
    }

    /**
     * Mendapatkan semua record unit kerja yang terkait dengan ID dalam array unit_kerja_id
     * Ini lebih sesuai jika unit_kerja_id bisa menampung banyak relasi.
     */
    public function allUnitKerja()
    {
        $unitIds = is_array($this->unit_kerja_id) ? $this->unit_kerja_id : [$this->unit_kerja_id];
        
        // Penting: Pastikan SimpegUnitKerja diimport jika ingin digunakan di sini
        return \App\Models\SimpegUnitKerja::whereIn('kode_unit', $unitIds)->get();
    }

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