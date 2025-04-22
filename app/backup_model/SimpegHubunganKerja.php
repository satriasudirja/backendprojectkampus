<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegHubunganKerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_hubungan_kerja';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama_hub_kerja',
        'status_aktif',
        'pns',
    ];
}
