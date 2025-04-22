<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DataPublikasi extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_publikasi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pegawai_id',
        'jenis_publikasi_id',
        'jenis_layanan_id',
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
        'keterangan',
        'no_sk_penugasan',
        'tgl_input',
        'status_pengajuan'
    ];

    protected $casts = [
        'tgl_terbit' => 'date',
        'tgl_input' => 'date',
        'seminar' => 'boolean',
        'prosiding' => 'boolean',
        'volume' => 'integer',
        'nomor' => 'integer',
        'jumlah_halaman' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    // Relasi ke tabel jenis publikasi
    public function jenisPublikasi()
    {
        return $this->belongsTo(JenisPublikasi::class, 'jenis_publikasi_id');
    }

    // Relasi ke tabel jenis layanan
    public function jenisLayanan()
    {
        return $this->belongsTo(JenisLayanan::class, 'jenis_layanan_id');
    }
}