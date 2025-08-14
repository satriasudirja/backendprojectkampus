<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisPenghargaan extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_jenis_penghargaan';
    protected $primaryKey = 'kode';

    protected $fillable = [
        'kode',
        'nama',
    ];
}
