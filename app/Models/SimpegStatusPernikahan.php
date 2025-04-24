<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegStatusPernikahan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_status_pernikahan';

    protected $fillable = [
        'kode_status',
        'nama_status',
    ];
}
