<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingKehadiran extends Model
{
    use HasFactory;

    protected $table = 'setting_kehadiran';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
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

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'float',
        'berlaku_keterlambatan' => 'boolean',
        'berlaku_pulang_cepat' => 'boolean',
        'wajib_foto' => 'boolean',
        'wajib_isi_rencana_kegiatan' => 'boolean',
        'wajib_isi_realisasi_kegiatan' => 'boolean',
        'wajib_presensi_dilokasi' => 'boolean'
    ];
}
