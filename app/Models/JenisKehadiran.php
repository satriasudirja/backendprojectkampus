<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisKehadiran extends Model
{
    protected $table = 'simpeg_jenis_kehadiran';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode_jenis',
        'nama_jenis'
    ];

    public function jenisIzin()
    {
        return $this->hasMany(JenisIzin::class);
    }
}
