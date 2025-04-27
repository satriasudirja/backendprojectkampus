<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisKenaikanPangkat extends Model
{
    protected $table = 'simpeg_jenis_kenaikan_pangkat';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'jenis_pangkat'
    ];
}
