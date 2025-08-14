<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataRiwayatPekerjaan extends Model
{
    use HasUuids;
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
         'status_pengajuan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
          'keterangan'
        








          
    ];

    protected $casts = [
        'mulai_bekerja' => 'date',
        'tgl_ditolak' => 'datetime',
        'selesai_bekerja' => 'date',
           'tgl_diajukan'=> 'datetime',
        'tgl_disetujui'=> 'datetime',
        'tgl_input' => 'date',
        'area_pekerjaan' => 'boolean'
    ];



    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // FIXED: Ubah dari hasMany menjadi morphMany untuk polymorphic relationship
    public function dataPendukung()
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }
}