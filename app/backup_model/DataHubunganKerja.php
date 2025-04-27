<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataHubunganKerja extends Model
{
    use HasFactory;

    protected $table = 'data_hubungan_kerja';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function hubunganKerja()
    {
        return $this->belongsTo(HubunganKerja::class, 'hubungan_kerja_id');
    }

    public function statusAktif()
    {
        return $this->belongsTo(StatusAktif::class, 'status_aktif_id');
    }
}