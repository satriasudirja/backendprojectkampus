<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GelarAkademik extends Model
{
    use HasFactory;

    protected $table = 'gelar_akademik';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'gelar',
        'nama_gelar',


    ];
}