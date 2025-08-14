<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDaftarJenisPkm extends Model
{
    use HasUuids;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'daftar_jenis_pkm';

    protected $primaryKey = 'id';
 
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