<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JamKerja extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis_jam_kerja',
        'jam_normal',
        'jam_datang',
        'jam_pulang',
    ];
}