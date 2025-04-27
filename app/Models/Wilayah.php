<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    protected $table = 'wilayah';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Kode_negara',
        'nama_negara',
        'kode_etnis',
        'kode_provinsi',
        'nama_provinsi',
        'kode_kab_kota',
        'nama_kab_kota',
        'kode_kecamatan',
        'nama_kecamatan',
        'jenis_wilayah',
    ];
}
