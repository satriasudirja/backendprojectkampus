<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPendidikan extends Model
{
    use HasUuids;
    use HasFactory;

    protected $table = 'simpeg_data_pendidikan';

    protected $primaryKey = 'id';
    
    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_kegiatan',
        'status_pengajuan',
        // 'tanggal_pengajuan', // <-- REKOMENDASI: Hapus jika mengikuti saran di migrasi
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
        'tanggal_pelaksanaan',
        'tgl_diajukan',      // Ditambahkan
        'tgl_disetujui',     // Ditambahkan
        'tgl_ditolak'        // Ditambahkan
    ];

    protected $casts = [
        // 'tanggal_pengajuan' => 'date', // <-- REKOMENDASI: Hapus jika mengikuti saran di migrasi
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'tanggal_pelaksanaan' => 'date',
        'lama_kegiatan' => 'integer',
        'tgl_diajukan' => 'datetime',  // Ditambahkan
        'tgl_disetujui' => 'datetime', // Ditambahkan
        'tgl_ditolak' => 'datetime'   // Ditambahkan
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}