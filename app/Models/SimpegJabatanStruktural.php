<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanStruktural extends Model
{
    use HasFactory, SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_jabatan_struktural';
    protected $fillable = [
        'unit_kerja_id',
        'jenis_jabatan_struktural_id',
        'pangkat_id',
        'eselon_id',
        'kode',
        'singkatan',
        'alamat_email',
        'beban_sks',
        'is_pimpinan',
        'aktif',
        'keterangan',
        'parent_jabatan',
        'tunjangan',
    ];

    protected $casts = [
        'is_pimpinan' => 'boolean',
        'aktif' => 'boolean',
        'tgl_ditolak' => 'datetime',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
    ];

    /**
     * Relasi ke unit kerja
     */
    public function unitKerja()
    {
        return $this->belongsTo(SimpegUnitKerja::class, 'unit_kerja_id');
    }

    /**
     * Relasi ke jenis jabatan struktural
     */
    public function jenisJabatanStruktural()
    {
        return $this->belongsTo(JenisJabatanStruktural::class, 'jenis_jabatan_struktural_id');
    }

    /**
     * Relasi ke pangkat
     */
    public function pangkat()
    {
        return $this->belongsTo(SimpegMasterPangkat::class, 'pangkat_id');
    }

    /**
     * Relasi ke eselon
     */
    public function eselon()
    {
        return $this->belongsTo(SimpegEselon::class, 'eselon_id');
    }

    /**
     * Relasi ke jabatan parent (self-reference)
     * parent_jabatan merujuk ke kode jabatan struktural parent
     */
    public function parent()
    {
        return $this->belongsTo(SimpegJabatanStruktural::class, 'parent_jabatan', 'kode');
    }

    /**
     * Relasi ke jabatan children (self-reference)
     * Jabatan yang memiliki parent_jabatan = kode jabatan ini
     */
    public function children()
    {
        return $this->hasMany(SimpegJabatanStruktural::class, 'parent_jabatan', 'kode');
    }

    /**
     * Relasi ke data jabatan struktural pegawai
     * Pegawai yang pernah/sedang menjabat di jabatan struktural ini
     */
    public function dataJabatanStruktural()
    {
        return $this->hasMany(SimpegDataJabatanStruktural::class, 'jabatan_struktural_id');
    }

    /**
     * Relasi ke data jabatan struktural yang aktif
     * Pegawai yang sedang menjabat di jabatan struktural ini (tgl_selesai = null)
     */
    public function dataJabatanStrukturalAktif()
    {
        return $this->hasMany(SimpegDataJabatanStruktural::class, 'jabatan_struktural_id')
                    ->whereNull('tgl_selesai');
    }

    /**
     * Relasi ke pegawai yang sedang menjabat (through dataJabatanStruktural)
     */
    public function pegawaiAktif()
    {
        return $this->hasManyThrough(
            SimpegPegawai::class,
            SimpegDataJabatanStruktural::class,
            'jabatan_struktural_id', // foreign key di data_jabatan_struktural
            'id', // foreign key di pegawai
            'id', // local key di jabatan_struktural
            'pegawai_id' // local key di data_jabatan_struktural
        )->whereNull('simpeg_data_jabatan_struktural.tgl_selesai');
    }

    /**
     * Scope untuk jabatan aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /**
     * Scope untuk jabatan pimpinan
     */
    public function scopePimpinan($query)
    {
        return $query->where('is_pimpinan', true);
    }

    /**
     * Method untuk mendapatkan hierarki lengkap ke atas
     */
    public function getHierarki()
    {
        $hierarki = collect([$this]);
        $current = $this;
        
        while ($current->parent) {
            $current = $current->parent;
            $hierarki->prepend($current);
        }
        
        return $hierarki;
    }

    /**
     * Method untuk mendapatkan semua descendants (child dan cucu)
     */
    public function getAllDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Method untuk mengecek apakah jabatan ini adalah parent dari jabatan lain
     */
    public function isParentOf($jabatan)
    {
        if ($jabatan instanceof SimpegJabatanStruktural) {
            return $jabatan->parent_jabatan === $this->kode;
        }
        
        // Jika parameter adalah kode jabatan
        return SimpegJabatanStruktural::where('parent_jabatan', $this->kode)
                                      ->where('kode', $jabatan)
                                      ->exists();
    }

    /**
     * Method untuk mengecek apakah jabatan ini adalah child dari jabatan lain
     */
    public function isChildOf($jabatan)
    {
        if ($jabatan instanceof SimpegJabatanStruktural) {
            return $this->parent_jabatan === $jabatan->kode;
        }
        
        // Jika parameter adalah kode jabatan
        return $this->parent_jabatan === $jabatan;
    }
}