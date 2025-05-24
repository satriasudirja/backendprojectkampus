<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisKenaikanPangkat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_jenis_kenaikan_pangkat';
    
    protected $fillable = [
        'kode',
        'nama_kenaikan',
        'deskripsi',
        'is_aktif'
    ];

    protected $casts = [
        'is_aktif' => 'boolean'
    ];

    /**
     * Relationship to DataPangkat
     */
    public function dataPangkat()
    {
        return $this->hasMany(SimpegDataPangkat::class, 'jenis_kenaikan_pangkat_id');
    }
}