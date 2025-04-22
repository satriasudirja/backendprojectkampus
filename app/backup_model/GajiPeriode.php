<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GajiPeriode extends Model
{
    protected $table = 'simpeg_gaji_periode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nama_periode', 'tgl_mulai', 'tgl_selesai', 'status'
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date'
    ];

    public function gaji()
    {
        return $this->hasMany(Gaji::class, 'periode_id');
    }
}
