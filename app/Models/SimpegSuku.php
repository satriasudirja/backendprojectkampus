<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegSuku extends Model
{
    use HasFactory;

    protected $table = 'simpeg_suku';

    protected $fillable = [
        'nama_suku',
    ];
}
