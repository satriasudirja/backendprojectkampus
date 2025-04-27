<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegDataSetifikasi extends Model
{
    protected $table = 'simpeg_data_setifikasi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_sertifikasi_id',
        'bidang_ilmu_id',
        'no_sertifikasi',
        'tgl_sertifikasi',
        'no_registrasi',
        'no_peserta',
        'peran',
        'penyelenggara',
        'tempat',
        'lingkup',
        'tgl_input'
    ];


}
