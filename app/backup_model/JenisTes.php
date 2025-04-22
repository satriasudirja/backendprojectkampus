<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisTes extends Model
{
    use HasFactory;

    protected $table = 'jenis_tes';
    protected $primaryKey = 'kode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode',
        'jenis_tes',
        'nilai_minimal',
        'nilai_maksimal',
    ];
}