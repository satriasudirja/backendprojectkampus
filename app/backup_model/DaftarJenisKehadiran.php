<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaftarJenisKehadiran extends Model
{
    use HasFactory;

    protected $primaryKey = 'kode'; // Menentukan primary key
    public $incrementing = false; // Primary key tidak auto-increment
    protected $keyType = 'string'; // Tipe data primary key

    protected $fillable = [
        'kode',
        'jenis_kehadiran',
        'warna',
    ];
}
