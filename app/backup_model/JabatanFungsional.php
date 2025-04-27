<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JabatanFungsional extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan_fungsional';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'jabatan_akademik_id',
        'pangkat_id',
        'kode',
        'nama_jabatan_fungsional',
        'kode_jabatan_akademik',
        'pangkat',
        'angka_kredit',
        'usia_pensiun',
        'keterangan'
    ];

    protected $casts = [
        'usia_pensiun' => 'integer'
    ];

    public function jabatanAkademik()
    {
        return $this->belongsTo(JabatanAkademik::class);
    }

    public function pangkat()
    {
        return $this->belongsTo(Pangkat::class);
    }
}