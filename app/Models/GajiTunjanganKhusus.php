<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GajiTunjanganKhusus extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_tunjangan_khusus';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pegawai_id',
        'komponen_id',
        'jumlah',
        'tgl_mulai',
        'tgl_selesai',
        'keterangan'
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'jumlah' => 'float'
    ];

    // Relasi ke pegawai
    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    // Relasi ke komponen gaji
    public function komponen()
    {
        return $this->belongsTo(KomponenGaji::class);
    }
}