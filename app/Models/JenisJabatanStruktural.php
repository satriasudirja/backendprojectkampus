<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisJabatanStruktural extends Model
{
    protected $table = 'simpeg_jenis_jabatan_struktural';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'jenis_jabatan_struktural'
    ];
}