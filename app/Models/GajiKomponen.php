<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_komponen';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode_komponen',
        'nama_komponen',
        'jenis',
        'rumus'
    ];

    // Relasi ke tunjangan khusus
    public function tunjanganKhusus()
    {
        return $this->hasMany(TunjanganKhusus::class, 'komponen_id');
    }
}