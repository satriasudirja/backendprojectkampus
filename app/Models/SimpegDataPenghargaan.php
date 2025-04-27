<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPenghargaan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_penghargaan';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'kategori_penghargaan',
        'tingkat_penghargaan',
        'jenis_penghargaan',
        'nama_penghargaan',
        'tanggal',
        'instansi_pemberi',
        'status_pengajuan'
    ];

    protected $casts = [
        'tanggal' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}