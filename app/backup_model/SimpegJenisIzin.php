<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegJenisIzin extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jenis_izin';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'jenis_kehadiran_id',
        'kode',
        'jenis_izin',
        'status_presensi',
        'izin_max',
        'potong_cuti'
    ];
}

