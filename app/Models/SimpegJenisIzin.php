<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisIzin extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_jenis_izin';

    protected $fillable = [
        'jenis_kehadiran_id',
        'kode',
        'jenis_izin', // PERBAIKAN: Menggunakan nama field yang benar
        'status_presensi',
        'izin_max',
        'potong_cuti',
    ];

    protected $casts = [
        'potong_cuti' => 'boolean',
    ];

    public function jenisKehadiran()
    {
        return $this->belongsTo(SimpegJenisKehadiran::class, 'jenis_kehadiran_id');
    }
}
