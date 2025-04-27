<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodeGaji extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_periode';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nama_periode',
        'tgl_mulai',
        'tgl_selesai',
        'status'
    ];

    // Relasi ke slip gaji
    public function slipGaji()
    {
        return $this->hasMany(SlipGaji::class, 'periode_id');
    }
}