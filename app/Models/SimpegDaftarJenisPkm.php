<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDaftarJenisPkm extends Model
{
    use HasFactory;

    protected $table = 'simpeg_daftar_jenis_pkm';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'kode',
        'nama_pkm'
    ];

    // // Relasi ke tabel PKM jika diperlukan
    // public function pkmRecords()
    // {
    //     return $this->hasMany(SimpegPkmRecord::class, 'jenis_pkm_id');
    // }
}