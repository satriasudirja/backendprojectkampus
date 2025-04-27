<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenjangPendidikan extends Model
{
    use HasFactory;

    protected $table = 'jenjang_pendidikan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'jenjang_singkatan',
        'nama_jenjang',
        'nama_jenjang_eng',
        'urutan_jenjang_pendidikan',
        'perguruan_tinggi',
        'pasca_sarjana'
    ];

    protected $casts = [
        'perguruan_tinggi' => 'boolean',
        'pasca_sarjana' => 'boolean'
    ];
}
