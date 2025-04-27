<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisPublikasi extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_jenis_publikasi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'jenis_publikasi',
    ];
}
