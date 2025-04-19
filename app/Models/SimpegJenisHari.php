<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegJenisHari extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jenis_hari';
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'kode',
        'nama_hari',
        'jenis_hari',
    ];
}

