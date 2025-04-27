<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisPenghargaan extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_jenis_penghargaan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama_penghargaan',
    ];
}
