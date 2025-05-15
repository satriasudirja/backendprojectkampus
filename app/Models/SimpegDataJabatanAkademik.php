<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataJabatanAkademik extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_jabatan_akademik';

  

    protected $fillable = [
        'id',
        'pegawai_id',
        'jabatan_akademik_id',
        'tmt_jabatan',
        'no_sk',
        'tgl_sk',
        'pejabat_penetap',
        'file_jabatan',
        'tgl_input',
        'status_pengajuan'
    ];

    protected $casts = [
        'tmt_jabatan' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke tabel jabatan akademik
    public function jabatanAkademik()
    {
        return $this->belongsTo(SimpegJabatanAkademik::class, 'jabatan_akademik_id');
    }
}