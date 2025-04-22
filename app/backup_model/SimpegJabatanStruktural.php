<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegJabatanStruktural extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan_struktural';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'unit_kerja_id',
        'jenis_jabatan_struktural_id',
        'pangkat_id',
        'eselon_id',
        'kode',
        'singkatan',
        'alamat_email',
        'beban_sks',
        'is_pimpinan',
        'aktif',
        'keterangan',
        'parent_jabatan',
    ];
}
