<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HubunganKerja extends Model
{
    use SoftDeletes;
    protected $table = 'simpeg_hubungan_kerja';
    protected $primaryKey = 'id';


    protected $fillable = [
        'kode',
        'nama_hub_kerja',
        'status_aktif',
        'pns'
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'pns' => 'boolean'
    ];
}