<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDaftarJenisSk extends Model
{
    use SoftDeletes;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_daftar_jenis_sk';

    protected $primaryKey = 'id';
    

    protected $fillable = [
        'id',
        'kode',
        'jenis_sk',
        'col_4' // Kolom tambahan (sesuaikan nama jika diperlukan)
    ];

    // Relasi ke tabel SK jika diperlukan
    public function skRecords()
    {
        return $this->hasMany(SimpegDataPangkat::class, 'jenis_sk_id');
    }
}