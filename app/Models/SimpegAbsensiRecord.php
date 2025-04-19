<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegAbsensiRecord extends Model
{
    use HasFactory;

    protected $table = 'simpeg_absensi_record';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pegawai_id',
        'setting_kehadiran_id',
        'jenis_kehadiran_id',
        'tanggal_absensi',
        'jam_masuk',
        'jam_keluar',
        'terlambat',
        'pulang_awal',
        'check_sum_absensi'
    ];
}

