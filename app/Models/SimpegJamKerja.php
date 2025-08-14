<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJamKerja extends Model
{
    use HasFactory, SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_jam_kerja';
    protected $primaryKey = 'id';

    // PERBAIKAN: Disesuaikan dengan struktur yang Anda berikan
    protected $fillable = [
        'jenis_jam_kerja',
        'jam_normal',
        'jam_datang', // Nama kolom yang benar
        'jam_pulang', // Nama kolom yang benar
        'is_default',
        'is_active',
        'toleransi_terlambat',  // dalam menit
        'toleransi_pulang_awal', // dalam menit
    ];

    protected $casts = [
        'jam_normal' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'toleransi_terlambat' => 'integer',
        'toleransi_pulang_awal' => 'integer',
        // Casting untuk jam_datang dan jam_pulang jika formatnya H:i:s
        'jam_datang' => 'datetime:H:i:s',
        'jam_pulang' => 'datetime:H:i:s',
    ];
}
