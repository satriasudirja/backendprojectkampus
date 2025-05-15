<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegJabatanFungsional extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan_fungsional';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'jabatan_akademik_id',
        'pangkat_id',
        'kode',
        'nama_jabatan_fungsional',
        'kode_jabatan_akademik',
        'pangkat',
        'angka_kredit',
        'usia_pensiun',
        'keterangan',
    ];


    public function jabatanAkademik()
{
    return $this->belongsTo(SimpegJabatanAkademik::class, 'jabatan_akademik_id');
}
}
