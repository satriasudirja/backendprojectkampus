<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaftarCuti extends Model
{
    use HasFactory;

    protected $primaryKey = 'kode'; // Menentukan primary key
    public $incrementing = false; // Primary key tidak auto-increment
    protected $keyType = 'string'; // Tipe data primary key

    protected $fillable = [
        'kode',
        'nama_jenis_cuti',
        'standar_cuti',
        'format_nomor_surat',
        'keterangan',
    ];
}