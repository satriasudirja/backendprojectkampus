<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GajiLembur extends Model
{
    protected $table = 'simpeg_gaji_lembur';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pegawai_id', 'tanggal', 'jam_mulai', 'jam_selesai',
        'durasi', 'upah_perjam', 'total_upah', 'status'
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
        return $this->belongsTo(Pegawai::class);
    }
}