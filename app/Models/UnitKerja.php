<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    protected $table = 'simpeg_unit_kerja';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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
        'gedung'
    ];

    protected $casts = [
        'tanggal_akreditasi' => 'date',
        'tanggal_sk_pendirian' => 'date'
    ];

    // Relasi ke parent unit
    public function parent()
    {
        return $this->belongsTo(UnitKerja::class, 'parent_unit_id');
    }

    // Relasi ke child units
    public function children()
    {
        return $this->hasMany(UnitKerja::class, 'parent_unit_id');
    }

    // Relasi ke jenis unit
    public function jenisUnit()
    {
        return $this->belongsTo(JenisUnit::class, 'jenis_unit_id');
    }

    // Relasi ke tingkat pendidikan
    public function tingkatPendidikan()
    {
        return $this->belongsTo(TingkatPendidikan::class, 'tk_pendidikan_id');
    }

    // Relasi ke akreditasi
    public function akreditasi()
    {
        return $this->belongsTo(Akreditasi::class, 'akreditasi_id');
    }
}