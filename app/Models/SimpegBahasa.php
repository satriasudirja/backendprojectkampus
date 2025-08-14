<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegBahasa extends Model
{
    use HasUuids;
    // Nama tabel sesuai migration
    use SoftDeletes;
    protected $table = 'bahasa';

    // Konfigurasi primary key (bigIncrements)
    protected $primaryKey = 'id';
  
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