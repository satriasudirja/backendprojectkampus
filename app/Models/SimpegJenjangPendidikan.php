<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenjangPendidikan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_jenjang_pendidikan';
    protected $primaryKey = 'id';


    protected $fillable = [
        'jenjang_singkatan',
        'jenjang_pendidikan', 
        'nama_jenjang_pendidikan_eng', 
        'urutan_jenjang_pendidikan',
        'perguruan_tinggi',
        'pasca_sarjana'
    ];
    

    protected $casts = [
        'perguruan_tinggi' => 'boolean',
        'pasca_sarjana' => 'boolean'
    ];
}
