<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataDiklat extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_diklat';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

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
        'no_sentifikat',
        'tgl_sentifikat',
        'tahun_penyelenggaraan',
        'tempat',
        'tgl_mulai',
        'tgl_selesai',
        'sk_penugasan',
        'tgl_input'
    ];

    protected $casts = [
        'tgl_sentifikat' => 'date',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_input' => 'date',
        'jumlah_jam' => 'integer'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}