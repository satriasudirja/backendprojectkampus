<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SimpegDaftarJenisLuaran extends Model
{
    use HasUuids;
    use SoftDeletes;
    use HasFactory;

    protected $table = 'simpeg_daftar_jenis_luaran';

    protected $primaryKey = 'id';
   
    protected $fillable = [
        'id',
        'kode',
        'jenis_luaran'
    ];

    // Relasi ke tabel lain jika diperlukan
    public function luaranRecords()
    {
        return $this->hasMany(SimpegDataPublikasi::class, 'jenis_luaran_id');
    }
}