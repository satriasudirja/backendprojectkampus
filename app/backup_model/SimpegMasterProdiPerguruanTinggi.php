<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegMasterProdiPerguruanTinggi extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_prodi_perguruan_tinggi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'perguruan_tinggi_id', 'jenjang_pendidikan_id', 'kode',
        'nama_prodi', 'jenjang', 'alamat', 'no_telp'
    ];


}
