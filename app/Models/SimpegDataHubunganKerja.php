<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataHubunganKerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_hubungan_kerja';


 

    protected $fillable = [
        'id',
        'no_sk',
        'tgl_sk',
        'tgl_awal',
        'tgl_akhir',
        'pejabat_penetap',
        'file_hubungan_kerja',
        'tgl_input',
        'hubungan_kerja_id',
        'status_aktif_id',
        'pegawai_id'
    ];

    protected $casts = [
        'tgl_sk' => 'date',
        'tgl_awal' => 'date',
        'tgl_akhir' => 'date',
        'tgl_input' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis hubungan kerja
    public function hubunganKerja()
    {
        return $this->belongsTo(HubunganKerja::class, 'hubungan_kerja_id');
    }

    // Relasi ke status aktif
    public function statusAktif()
    {
        return $this->belongsTo(SimpegStatusAktif::class, 'status_aktif_id');
    }
}