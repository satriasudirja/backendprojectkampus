<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJamKerja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_jam_kerja';
    protected $primaryKey = 'id';


protected $fillable = [
    'jenis_jam_kerja',
    'jam_normal',
    'jam_masuk',        // Format: H:i:s
    'jam_keluar',       // Format: H:i:s
    'is_default',
    'is_active',
    'toleransi_terlambat',    // dalam menit
    'toleransi_pulang_awal',  // dalam menit
];

protected $casts = [
    'jam_normal' => 'boolean',
    'is_default' => 'boolean',
    'is_active' => 'boolean',
    'toleransi_terlambat' => 'integer',
    'toleransi_pulang_awal' => 'integer',
];
}
