<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterPerguruanTinggi extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_master_perguruan_tinggi';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'kode',
        'nama_universitas',
        'alamat',
        'no_telp'
    ];
}