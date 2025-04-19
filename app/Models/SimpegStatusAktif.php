<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SimpegStatusAktif extends Model
{
    protected $table = 'simpeg_status_aktif';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'kode',
        'nama_status_aktif',
        'status_keluar',
    ];
}
