<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataJabatanStruktural extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_jabatan_struktural';

    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'pegawai_id',
        'jabatan_struktural_id',
        'tgl_mulai',
        'tgl_selesai',
        'no_sk',
        'tgl_sk',
        'pejabat_penetap',
        'file_jabatan',
        'tgl_input',
        'status_pengajuan',
        'deleted_at',
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke tabel jabatan struktural
    public function jabatanStruktural()
    {
        return $this->belongsTo(SimpegJabatanStruktural::class, 'jabatan_struktural_id');
    }
}