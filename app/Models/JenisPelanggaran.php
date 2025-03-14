<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisPelanggaran extends Model
{
    use HasFactory;

    protected $primaryKey = 'kode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode',
        'nama_pelanggaran',
    ];
}