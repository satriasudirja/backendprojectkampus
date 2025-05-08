<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJamKerja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_jam_kerja';
    protected $primaryKey = 'id';


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
