<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterProdiPerguruanTinggi extends Model
{
    use HasFactory;

    protected $table = 'master_prodi_perguruan_tinggi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'perguruan_tinggi_id',
        'jenjang_pendidikan_id',
        'kode',
        'nama_prodi',
        'alamat',
        'no_telp'
    ];

    public function perguruanTinggi()
    {
        return $this->belongsTo(MasterPerguruanTinggi::class, 'perguruan_tinggi_id');
    }

    public function jenjangPendidikan()
    {
        return $this->belongsTo(JenjangPendidikan::class, 'jenjang_pendidikan_id');
    }
}
