<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDaftarJenisLuaran extends Model
{
    use HasFactory;

    protected $table = 'simpeg_daftar_jenis_luaran';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

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