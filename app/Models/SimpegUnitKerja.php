<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegUnitKerja extends Model
{
    use HasFactory;

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
}
