<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubunganKerja extends Model
{
    protected $table = 'simpeg_hubungan_kerja';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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