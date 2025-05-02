<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegHubunganKerja extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'simpeg_hubungan_kerja';

    
    protected $fillable = [
        'kode',
        'nama_hub_kerja',
        'status_aktif',
        'pns',
    ];
}
