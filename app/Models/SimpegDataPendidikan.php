<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPendidikan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_pendidikan';

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
        'perguruan_tinggi_sasaran',
        'bidang_tugas',
        'lama_kegiatan',
        'nama_kegiatan',
        'jenis_bahan_ajar',
        'judul_bahan_ajar',
        'penerbit',
        'penyelenggara',
        'tugas_tambahan',
        'tanggal_mulai',
        'tanggal_akhir',
        'tanggal_pelaksanaan'
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'tanggal_pelaksanaan' => 'date',
        'lama_kegiatan' => 'integer'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}