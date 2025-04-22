<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegSettingKehadiran extends Model
{
    use HasFactory;

    protected $table = 'simpeg_setting_kehadiran';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nama_gedung',
        'latitude',
        'longitude',
        'radius',
        'berlaku_keterlambatan',
        'toleransi_terlambat',
        'berlaku_pulang_cepat',
        'toleransi_pulang_cepat',
        'wajib_foto',
        'wajib_isi_rencana_kegiatan',
        'wajib_isi_realisasi_kegiatan',
        'wajib_presensi_dilokasi'
    ];
}

