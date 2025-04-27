<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnivLuar extends Model
{
    use HasFactory;

    protected $table = 'univ_luar';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kode',
        'nama_univ',
        'alamat',
        'no_telp'
    ];
}
