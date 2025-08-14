<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegMasterJenisSertifikasi extends Model
{
    use HasFactory, SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_master_jenis_sertifikasi';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'kode',
        'nama_sertifikasi',
        'jenis_sertifikasi'
    ];
}
