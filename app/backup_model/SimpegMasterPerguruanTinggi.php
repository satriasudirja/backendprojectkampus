<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegMasterPerguruanTinggi extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_perguruan_tinggi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 
    'kode', 
    'nama_universitas', 
    'alamat', 
    'no_telp'];

}