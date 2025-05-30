<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SimpegUnitKerja extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'simpeg_unit_kerja';

    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'parent_unit_id',
        'jenis_unit_id',
        'tk_pendidikan_id',
        'alamat',
        'telepon',
        'website',
        'alamat_email',
        'akreditasi_id',
        'no_sk_akreditasi',
        'tanggal_akreditasi',
        'no_sk_pendirian',
        'tanggal_sk_pendirian',
        'gedung',
        'deleted_at',
    ];

    /**
     * Relasi ke unit induk (self-reference)
     */
    public function parent()
    {
        return $this->belongsTo(SimpegUnitKerja::class, 'parent_unit_id', 'kode_unit');
    }

    /**
     * Relasi ke unit anak (self-reference)
     */
    public function children()
    {
        return $this->hasMany(SimpegUnitKerja::class, 'parent_unit_id', 'kode_unit');
    }
        public function pegawai()
    {
        return $this->hasMany(SimpegPegawai::class, 'unit_kerja_id', 'kode_unit');
    }
    
    public function parentUnit()
    {
        return $this->belongsTo(self::class, 'parent_unit_id', 'kode_unit');
    }
    
    public function childUnits()
    {
        return $this->hasMany(self::class, 'parent_unit_id', 'kode_unit');
    }
    
    public function berita()
    {
        return $this->hasMany(SimpegBerita::class, 'unit_kerja_id', 'kode_unit');
    }
    
}
