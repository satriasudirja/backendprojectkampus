<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegUnivLuar extends Model
{
    use HasUuids;
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_univ_luar';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'kode',
        'nama_universitas',
        'alamat',
        'no_telp'
    ];
}