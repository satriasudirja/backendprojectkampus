<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RumpunBidangIlmu extends Model
{
    use HasFactory;

    protected $table = 'rumpun_bidang_ilmu';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kode',
        'nama_bidang',
        'parent_category',
        'sub_parent_category'
    ];
}