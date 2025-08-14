<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPenelitian extends Model
{
    use HasUuids;
    use HasFactory;

    protected $table = 'simpeg_data_penelitian';

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
        'judul_penelitian',
        'kelompok_bidang',
        'jenis_penelitian',
        'tanggal_mulai',
        'tanggal_akhir',
        'perguruan_tinggi_afiliasi',
        'judul_publikasi',
        'jenis_publikasi',
        'tanggal_terbit',
        'doi',
        'isbn',
        'issn',
        'e_issn',
        'penerbit',
        'edisi',
        'volume',
        'jumlah_halaman'
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'tanggal_terbit' => 'date',
        'tgl_ditolak' => 'datetime',
        'volume' => 'integer',
        
        'jumlah_halaman' => 'integer'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}