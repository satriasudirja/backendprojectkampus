<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegEselon extends Model
{
    use HasFactory;

    protected $table = 'simpeg_eselon';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama_eselon',
        'status',
    ];
}
