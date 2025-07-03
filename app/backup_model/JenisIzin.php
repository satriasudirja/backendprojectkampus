<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisIzin extends Model
{
    protected $table = 'simpeg_jenis_izin';


    protected $fillable = [
        'jenis_kehadiran_id',
        'kode',
        'jenis_izin',
        'status_presensi',
        'izin_max',
        'potong_cuti'
    ];

    protected $casts = [
        'potong_cuti' => 'boolean'
    ];

    public function jenisKehadiran()
    {
        return $this->belongsTo(SimpegJenisKehadiran::class);
    }
    
}
