<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegJenisKehadiran extends Model
{
    protected $table = 'simpeg_jenis_kehadiran';
  
    protected $fillable = [
        'kode_jenis',
        'nama_jenis'
    ];

    public function jenisIzin()
    {
        return $this->hasMany(JenisIzin::class);
    }
}
