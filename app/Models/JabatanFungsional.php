<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanFungsional extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_jabatan_fungsional';
    protected $primaryKey = 'id';
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
        return $this->belongsTo(JabatanAkademik::class, 'jabatan_akademik_id');
    }

    public function pangkat()
    {
        return $this->belongsTo(Pangkat::class, 'pangkat_id');
    }
}
