<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegMasterPangkat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_master_pangkat';
    protected $primaryKey = 'id';
 

    protected $fillable = [
        'id',
        'pangkat',
        'nama_golongan'
    ];
}
