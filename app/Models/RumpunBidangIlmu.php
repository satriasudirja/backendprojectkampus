<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RumpunBidangIlmu extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'rumpun_bidang_ilmu';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'kode',
        'nama_bidang',
        'parent_category',
        'sub_parent_category'
    ];
}