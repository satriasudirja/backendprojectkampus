<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisHari extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jenis_hari';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode',
        'nama_hari',
        'jenis_hari'
    ];

    protected $casts = [
        'jenis_hari' => 'boolean'
    ];
}