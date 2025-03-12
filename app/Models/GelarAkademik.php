<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GelarAkademik extends Model
{
    use HasFactory;

    protected $primaryKey = 'gelar';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'gelar',
        'nama_gelar',
    ];
}