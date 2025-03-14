<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GolonganDarah extends Model
{
    use HasFactory;


    protected $primaryKey = 'id'; 
    public $incrementing = true; 
    protected $keyType = 'integer'; 


    protected $fillable = [
        'golongan_darah',
    ];
}