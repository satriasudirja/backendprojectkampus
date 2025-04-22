<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SimpegJenisKenaikanPangkat extends Model
{
    protected $table = 'simpeg_jenis_kenaikan_pangkat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'kode',
        'jenis_pangkat',
    ];
}

