<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegJamKerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jam_kerja';
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'jenis_jam_kerja',
        'jam_normal',
        'jam_datang',
        'jam_pulang',
    ];
}

