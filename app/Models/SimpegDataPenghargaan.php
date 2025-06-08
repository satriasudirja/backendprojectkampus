<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPenghargaan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_penghargaan';

    protected $primaryKey = 'id';
    protected $fillable = [
        'pegawai_id',
    
         'jenis_penghargaan',
        'nama_penghargaan',
        'no_sk',
        'tanggal_sk',
        'tanggal_penghargaan',
        'keterangan'
    ];

    protected $casts = [
        'tanggal' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}