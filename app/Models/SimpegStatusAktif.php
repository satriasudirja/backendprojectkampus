<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegStatusAktif extends Model
{
    use HasFactory;

    protected $table = 'simpeg_status_aktif';

    protected $fillable = [
        'kode',
        'nama_status_aktif',
        'status_keluar',
    ];

    protected $casts = [
        'status_keluar' => 'boolean',
    ];
}
