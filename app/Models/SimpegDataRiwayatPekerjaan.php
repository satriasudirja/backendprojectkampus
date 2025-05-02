<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataRiwayatPekerjaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_riwayat_pekerjaan';

    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'pegawai_id',
        'bidang_usaha',
        'jenis_pekerjaan',
        'jabatan',
        'instansi',
        'divisi',
        'deskripsi',
        'mulai_bekerja',
        'selesai_bekerja',
        'area_pekerjaan',
        'tgl_input'
    ];

    protected $casts = [
        'mulai_bekerja' => 'date',
        'selesai_bekerja' => 'date',
        'tgl_input' => 'date',
        'area_pekerjaan' => 'boolean'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}