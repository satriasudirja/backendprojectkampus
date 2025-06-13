<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// Jika Anda menggunakan UUID, tambahkan trait untuk otomatisasi
// use App\Traits\Uuids; 

class SimpegJenisKenaikanPangkat extends Model
{
    use HasFactory, SoftDeletes;
   
    protected $table = 'simpeg_jenis_kenaikan_pangkat';

    protected $fillable = [
        'kode',
        'jenis_pangkat',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    
    public function dataPangkat()
    {
        // Pastikan foreign key-nya benar: 'jenis_kenaikan_pangkat_id'
        return $this->hasMany(SimpegDataPangkat::class, 'jenis_kenaikan_pangkat_id', 'id');
    }
}