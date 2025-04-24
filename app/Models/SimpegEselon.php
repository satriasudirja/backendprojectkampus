<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegEselon extends Model
{
    use HasFactory;

    protected $table = 'simpeg_eselon';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'kode',
        'nama_eselon',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    // Relasi ke tabel jabatan struktural jika diperlukan
    public function jabatanStruktural()
    {
        return $this->hasMany(SimpegJabatanStruktural::class, 'eselon_id');
    }
}