<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisHari extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_jenis_hari';
    protected $primaryKey = 'id';
    protected $fillable = [
        'kode',
        'nama_hari',
        'jenis_hari'
    ];

    protected $casts = [
        'jenis_hari' => 'boolean'
    ];
}