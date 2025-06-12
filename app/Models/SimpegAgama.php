<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegAgama extends Model
{
    use HasFactory, SoftDeletes;
    protected $primaryKey = 'id';

    protected $table = 'simpeg_agama';


    protected $fillable = [
        'kode',
        'nama_agama',
    ];


    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}