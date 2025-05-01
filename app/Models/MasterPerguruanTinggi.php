<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterPerguruanTinggi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_master_perguruan_tinggi';

    protected $fillable = [
        'kode',
        'nama_universitas',
        'alamat',
        'no_telp',
        'email',
        'website',
        'akreditasi',
        'is_aktif'
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope untuk filter perguruan tinggi yang aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    /**
     * Scope untuk pencarian berdasarkan nama universitas
     */
    public function scopeSearchByNama($query, $nama)
    {
        return $query->where('nama_universitas', 'ILIKE', "%{$nama}%");
    }

    /**
     * Scope untuk pencarian berdasarkan kode
     */
    public function scopeSearchByKode($query, $kode)
    {
        return $query->where('kode', 'ILIKE', "%{$kode}%");
    }

    /**
     * Relasi ke pendidikan pegawai (jika sudah dibuat)
     */
    // public function pendidikanPegawai()
    // {
    //     return $this->hasMany(SimpegPendidikanPegawai::class, 'perguruan_tinggi_id');
    // }
}