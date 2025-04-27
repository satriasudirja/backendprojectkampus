<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPerguruanTinggi extends Model
{
    use HasFactory;

    protected $table = 'master_perguruan_tinggi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kode',
        'nama_universitas',
        'alamat',
        'no_telp'
    ];
}