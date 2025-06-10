<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Ditambahkan
use Illuminate\Support\Str; // Ditambahkan untuk generate UUID

class SimpegDataPengabdian extends Model
{
    use HasFactory, SoftDeletes; // Ditambahkan

    protected $table = 'simpeg_data_pengabdian';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Boot the model.
     * Otomatis mengisi 'id' dengan UUID saat membuat record baru.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id', // ID harus fillable karena menggunakan UUID
        'pegawai_id',
        'jenis_kegiatan',
        'status_pengajuan',
        // 'tanggal_pengajuan', // Dihapus
        'sk_penugasan', // Diperbaiki
        'perguruan_tinggi_afiliasi',
        'kelompok_bidang',
        'jenis_penelitian',
        'judul_penelitian', // Diperbaiki
        'tanggal_mulai',
        'tanggal_akhir',
        'kategori_kegiatan',
        'jabatan_tugas',
        'lokasi_penugasan',
        'tgl_diajukan',     // Ditambahkan
        'tgl_disetujui',    // Ditambahkan
        'tgl_ditolak'       // Ditambahkan
    ];

    protected $casts = [
        // 'tanggal_pengajuan' => 'date', // Dihapus
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
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