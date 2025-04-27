<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegBahasa extends Model
{
    // Nama tabel sesuai migration
    use SoftDeletes;
    protected $table = 'bahasa';

    // Konfigurasi primary key (bigIncrements)
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';

    // Kolom yang bisa diisi massal
    protected $fillable = [
        'kode',
        'nama_bahasa'
    ];

    // Casting tipe data
    protected $casts = [
        'kode' => 'string',
        'nama_bahasa' => 'string',
    ];
}