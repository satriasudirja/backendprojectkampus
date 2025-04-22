<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataDiklat extends Model
{
    use HasFactory;

    protected $table = 'data_diklat';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_diklat',
        'kategori_diklat',
        'tingkat_diklat',
        'nama_diklat',
        'penyelenggara',
        'peran',
        'jumlah_jam',
        'no_sertifikat',
        'tgl_sertifikat',
        'tahun_penyelenggaraan',
        'tempat',
        'tgl_mulai',
        'tgl_selesai',
        'sk_penugasan',
        'tgl_input'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}