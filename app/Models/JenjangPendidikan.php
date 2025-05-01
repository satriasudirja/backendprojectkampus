<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenjangPendidikan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_jenjang_pendidikan';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'jenjang_singkatan',
        'jenjang_pendidikan',
        'nama_jenjang_pendidikan_eng',
        'urutan_jenjang_pendidikan',
        'perguruan_tinggi',
        'pasca_sarjana'
    ];

    protected $casts = [
        'perguruan_tinggi' => 'boolean',
        'pasca_sarjana' => 'boolean',
        'urutan_jenjang_pendidikan' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relasi ke program studi perguruan tinggi
     */
    public function programStudi()
    {
        return $this->hasMany(SimpegMasterProdiPerguruanTinggi::class, 'jenjang_pendidikan_id');
    }

    /**
     * Relasi ke pendidikan pegawai (jika sudah dibuat)
     */
    public function pendidikanPegawai()
    {
        return $this->hasMany(SimpegPendidikanPegawai::class, 'jenjang_pendidikan_id');
    }
}