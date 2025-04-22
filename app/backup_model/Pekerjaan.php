<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pekerjaan extends Model
{
    use HasFactory;


    protected $primaryKey = 'kode'; 
    public $incrementing = false; 
    protected $keyType = 'integer'; 

    
    protected $fillable = [
        'kode',
        'nama_pekerjaan',
    ];
}