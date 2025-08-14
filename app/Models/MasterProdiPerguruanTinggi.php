<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProdiPerguruanTinggi extends Model
{
    use HasFactory, SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_master_prodi_perguruan_tinggi';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'perguruan_tinggi_id',
        'jenjang_pendidikan_id',
        'kode',
        'nama_prodi',
        'alamat',
        'no_telp',
        'akreditasi',
        'is_aktif'
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relasi ke perguruan tinggi
     */
    public function perguruanTinggi()
    {
        return $this->belongsTo(MasterPerguruanTinggi::class, 'perguruan_tinggi_id');
    }

    /**
     * Relasi ke jenjang pendidikan
     */
    public function jenjangPendidikan()
    {
        return $this->belongsTo(JenjangPendidikan::class, 'jenjang_pendidikan_id');
    }

    /**
     * Relasi ke pendidikan pegawai (jika sudah dibuat)
     */
    // public function pendidikanPegawai()
    // {
    //     return $this->hasMany(SimpegPendidikanPegawai::class, 'prodi_id');
    // }
}