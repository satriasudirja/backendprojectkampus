<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanStruktural extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_jabatan_struktural';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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
    ];

    protected $casts = [
        'is_pimpinan' => 'boolean',
        'aktif' => 'boolean',
    ];

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function jenisJabatanStruktural()
    {
        return $this->belongsTo(JenisJabatanStruktural::class, 'jenis_jabatan_struktural_id');
    }

    public function pangkat()
    {
        return $this->belongsTo(Pangkat::class, 'pangkat_id');
    }

    public function eselon()
    {
        return $this->belongsTo(Eselon::class, 'eselon_id');
    }
}
