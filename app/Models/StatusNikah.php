<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusNikah extends Model
{
    use HasFactory;

    
    protected $primaryKey = 'kode'; 
    public $incrementing = false; 
    protected $keyType = 'string'; 

    
    protected $fillable = [
        'kode',
        'status',
    ];
}