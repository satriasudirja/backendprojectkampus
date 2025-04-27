<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegSuku extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_suku';

    protected $fillable = [
        'nama_suku',
    ];
}
