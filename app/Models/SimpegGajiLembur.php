<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegGajiLembur extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_lembur';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pegawai_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'durasi',
        'upah_perjam',
        'total_upah', // Diperbaiki dari 'total_upal'
        'status'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam_mulai' => 'datetime:H:i',
        'jam_selesai' => 'datetime:H:i',
        'durasi' => 'float',
        'upah_perjam' => 'float',
        'total_upah' => 'float'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
