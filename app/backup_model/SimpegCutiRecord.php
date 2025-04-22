<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegCutiRecord extends Model
{
    use HasFactory;

    protected $table = 'simpeg_cuti_record';
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'pegawai_id',
        'jenis_cuti_id',
        'no_urut_cuti',
        'tgl_mulai',
        'tgl_selesai',
        'jumlah_cuti',
        'alasan_cuti',
        'alamat',
        'no_telp',
        'file_cuti',
        'status_pengajuan',
    ];
}
