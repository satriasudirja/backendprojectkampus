<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPengabdian extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_pengabdian';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_kegiatan',
        'status_pengajuan',
        'tanggal_pengajuan',
        'sk_penugasan',
        'perguruan_tinggi_afiliasi',
        'kelompok_bidang',
        'jenis_penelitian',
        'judul_penelitian',
        'tanggal_mulai',
        'tanggal_akhir',
        'kategori_kegiatan',
        'jabatan_tugas',
        'lokasi_penugasan'
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}