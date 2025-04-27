<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegStatusPernikahan extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'simpeg_status_pernikahan';

    protected $fillable = [
        'kode_status',
        'nama_status',
    ];
}
