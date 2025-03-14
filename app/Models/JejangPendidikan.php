<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JejangPendidikan extends Model
{
    use HasFactory;


    protected $primaryKey = 'id'; 
    public $incrementing = true; 
    protected $keyType = 'integer'; 

    
    protected $fillable = [
        'jenjang',
        'nama',
        'jenjang_pendidikan',
        'nama_jenjang_pendidikan_en',
        'urutan_jenjang_pendidikan',
        'perguruan_tinggi',
        'pasca_sarjana',
    ];
}