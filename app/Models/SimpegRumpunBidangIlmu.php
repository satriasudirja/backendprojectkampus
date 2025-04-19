<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegRumpunBidangIlmu extends Model
{
    protected $table = 'simpeg_rumpun_bidang_ilmu';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'kode',
        'nama_bidang',
        'parent_category',
        'sub_parent_category'
    ];


}
