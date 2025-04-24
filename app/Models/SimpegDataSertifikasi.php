<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataSertifikasi extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_sertifikasi';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_sertifikasi_id',
        'bidang_ilmu_id',
        'no_sertifikasi',
        'tgl_sertifikasi',
        'no_registrasi',
        'no_peserta',
        'peran',
        'penyelenggara',
        'tempat',
        'lingkup',
        'tgl_input'
    ];

    protected $casts = [
        'tgl_sertifikasi' => 'date',
        'tgl_input' => 'date'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis sertifikasi
    public function jenisSertifikasi()
    {
        return $this->belongsTo(SimpegMasterJenisSertifikasi::class, 'jenis_sertifikasi_id');
    }

    // Relasi ke bidang ilmu
    public function bidangIlmu()
    {
        return $this->belongsTo(SimpegRumpunBidangIlmu::class, 'bidang_ilmu_id');
    }
}