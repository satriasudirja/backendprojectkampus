<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegMediaPublikasi extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_media_publikasi';

    protected $fillable = [
        'nama',
    ];
}
