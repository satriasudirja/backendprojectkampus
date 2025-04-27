<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SimpegMasterPangkat extends Model
{
    protected $table = 'simpeg_master_pangkat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'pangkat',
        'nama_golongan',
    ];
}
