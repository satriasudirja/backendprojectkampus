<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPublikasi extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_publikasi';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_publikasi_id',
        'jenis_luaran_id',
        'judul',
        'judul_asli',
        'nama_jurnal',
        'tgl_terbit',
        'penerbit',
        'edisi',
        'volume',
        'nomor',
        'halaman',
        'jumlah_halaman',
        'doi',
        'isbn',
        'issn',
        'e_issn',
        'seminar',
        'prosiding',
        'nomor_paten',
        'pemberi_paten',
        'keterangan', // Diperbaiki dari 'keteragan'
        'no_sk_penugasan',
        'tgl_input',
        'status_pengajuan'
    ];

    protected $casts = [
        'tgl_terbit' => 'date',
        'tgl_input' => 'date',
        'volume' => 'integer',
        'nomor' => 'integer',
        'jumlah_halaman' => 'integer',
        'seminar' => 'boolean',
        'prosiding' => 'boolean'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis publikasi
    public function jenisPublikasi()
    {
        return $this->belongsTo(SimpegJenisPublikasi::class, 'jenis_publikasi_id');
    }

    // Relasi ke jenis luaran
    public function jenisLuaran()
    {
        return $this->belongsTo(SimpegDaftarJenisLuaran::class, 'jenis_luaran_id');
    }
}