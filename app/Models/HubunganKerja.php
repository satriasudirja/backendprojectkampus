<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Testing\Fluent\Concerns\Has;

class HubunganKerja extends Model
{
    use SoftDeletes;
    use HasUuids;
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
     public function dataHubunganKerja()
    {
        return $this->hasMany(SimpegDataHubunganKerja::class, 'hubungan_kerja_id');
    }
}