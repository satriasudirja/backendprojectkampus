<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GelarAkademik extends Model
{
    use HasFactory;


    protected $primaryKey = 'gelar'; 
    public $incrementing = false; 
    protected $keyType = 'string'; 

    
    protected $fillable = [
        'gelar',
        'nama_gelar',
    ];
}