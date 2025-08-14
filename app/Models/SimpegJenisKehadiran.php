<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisKehadiran extends Model
{
    use SoftDeletes;
    use HasUuids;
    
    protected $table = 'simpeg_jenis_kehadiran';
 
    protected $fillable = [
        'kode_jenis',
        'nama_jenis',
        'warna',
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    public function jenisIzin()
    {
        // PERBAIKAN: Nama model yang benar adalah SimpegJenisIzin
        return $this->hasMany(SimpegJenisIzin::class, 'jenis_kehadiran_id');
    }
}
