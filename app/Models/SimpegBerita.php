<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegBerita extends Model
{
    // Nama tabel
    protected $table = 'simpeg_berita';

    // Konfigurasi primary key
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';

    // Kolom yang bisa diisi massal
    protected $fillable = [
        'unit_kerja_id',
        'judul',
        'slug',
        'tgl_posting',
        'tgl_expired',
        'gambar_featured',
        'status',
        'konten'
    ];

    // Casting tipe data
    protected $casts = [
        'tgl_posting' => 'date',
        'tgl_expired' => 'date',
    ];

    // Relasi ke model UnitKerja
    public function unitKerja()
    {
        return $this->belongsTo(SimpegUnitKerja::class, 'unit_kerja_id');
    }
}