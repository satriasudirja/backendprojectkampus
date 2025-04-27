<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPelanggaran extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_pelanggaran';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_pelanggaran_id',
        'tgl_pelanggaran',
        'no_sk',
        'tgl_sk'
    ];

    protected $casts = [
        'tgl_pelanggaran' => 'date',
        'tgl_sk' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis pelanggaran
    public function jenisPelanggaran()
    {
        return $this->belongsTo(SimpegJenisPelanggaran::class, 'jenis_pelanggaran_id');
    }
}