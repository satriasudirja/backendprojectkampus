<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JamKerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jam_kerja';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'jenis_jam_kerja',
        'jam_normal',
        'jam_datang',
        'jam_pulang'
    ];

    protected $casts = [
        'jam_normal' => 'boolean'
    ];
}