<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    protected $table = 'wilayah';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';

    protected $fillable = [
        'kode_negara',
        'nama_negara',
        'kode_etnis',
        'kode_provinsi',
        'nama_provinsi',
        'kode_kab_kota',
        'nama_kab_kota',
        'kode_kecamatan',
        'nama_kecamatan',
        'jenis_wilayah'
    ];

    // Scope untuk pencarian wilayah
    public function scopeProvinsi($query)
    {
        return $query->whereNotNull('kode_provinsi')
                    ->whereNull('kode_kab_kota')
                    ->whereNull('kode_kecamatan');
    }

    public function scopeKabupatenKota($query, $kodeProvinsi = null)
    {
        $query = $query->whereNotNull('kode_kab_kota')
                      ->whereNull('kode_kecamatan');

        if ($kodeProvinsi) {
            $query->where('kode_provinsi', $kodeProvinsi);
        }

        return $query;
    }

    public function scopeKecamatan($query, $kodeKabKota = null)
    {
        $query = $query->whereNotNull('kode_kecamatan');

        if ($kodeKabKota) {
            $query->where('kode_kab_kota', $kodeKabKota);
        }

        return $query;
    }

    // Relasi ke dirinya sendiri untuk hierarki wilayah
    public function provinsi()
    {
        return $this->whereNotNull('kode_provinsi')
                   ->whereNull('kode_kab_kota')
                   ->whereNull('kode_kecamatan');
    }

    public function kabupatenKota()
    {
        return $this->whereNotNull('kode_kab_kota')
                   ->whereNull('kode_kecamatan');
    }

    public function kecamatan()
    {
        return $this->whereNotNull('kode_kecamatan');
    }
}
