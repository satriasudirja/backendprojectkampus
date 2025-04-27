<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterJenisSertifikasi extends Model
{
    use HasFactory;

    protected $table = 'master_jenis_sertifikasi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kode',
        'nama_sertifikasi',
        'jenis_sertifikasi'
    ];
}
