<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Ditambahkan

class SimpegDataPenghargaan extends Model
{
    use HasFactory, SoftDeletes; // Ditambahkan

    protected $table = 'simpeg_data_penghargaan';

    protected $primaryKey = 'id';
    protected $fillable = [
        'pegawai_id',
        'jenis_penghargaan',
        'nama_penghargaan',
        'no_sk',
        'tanggal_sk',
        'tanggal_penghargaan',
        'file_penghargaan', // Ditambahkan
        'keterangan',
        'status_pengajuan', // Ditambahkan
        'tgl_diajukan',     // Ditambahkan
        'tgl_disetujui',    // Ditambahkan
        'tgl_ditolak',     // Ditambahkan
        // 'tgl_ditangguhkan',
        'tgl_input',
        'instansi_pemberi',
        'jenis_penghargaan_id',
    ];

    protected $casts = [
        'tanggal_sk' => 'date',
        'tanggal_penghargaan' => 'date',
        'tgl_diajukan' => 'datetime',  // Ditambahkan
        'tgl_disetujui' => 'datetime', // Ditambahkan
        'tgl_ditolak' => 'datetime',  // Ditambahkan
         'tgl_input' => 'datetime', // Ditambahkan
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
    
    public function jenisPenghargaan()
    {
        return $this->belongsTo(SimpegJenisPenghargaan::class, 'jenis_penghargaan_id');
    }

    protected static function booted()
    {
        static::creating(function ($penghargaan) {
            if (empty($penghargaan->tgl_input)) {
                $penghargaan->tgl_input = now();
            }
        });
    }
    
}


