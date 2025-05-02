<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegEselon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_eselon';

    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'kode',
        'nama_eselon',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];


}