<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJamKerja extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_jam_kerja';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'jenis_jam_kerja',
        'jam_normal',
        'jam_datang',
        'jam_pulang',
    ];

    protected $casts = [
        'jam_normal' => 'boolean',
    ];
}
