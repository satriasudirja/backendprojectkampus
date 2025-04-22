<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegMasterJenisSertifikasi extends Model
{
    protected $table = 'simpeg_master_jenis_sertifikasi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'kode',
        'nama_sertifikasi',
        'jenis_sertifikasi'
    ];

    
}
