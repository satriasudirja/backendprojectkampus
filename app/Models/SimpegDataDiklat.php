<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataDiklat extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'simpeg_data_diklat';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_diklat',
        'kategori_diklat',
        'tingkat_diklat',
        'nama_diklat',
        'penyelenggara',
        'peran',
        'jumlah_jam',
        'no_sertifikat',
        'tgl_sertifikat',
        'tahun_penyelenggaraan',
        'tempat',
        'tgl_mulai',
        'tgl_selesai',
        'sk_penugasan',
        'tgl_input',
        'status_pengajuan',
        'tgl_disetujui',
        'tgl_diajukan',
        'tgl_ditolak',
    ];

    protected $casts = [
        'tgl_sertifikat' => 'date',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_input' => 'date',
        'tgl_disetujui' => 'datetime',
        'tgl_diajukan' => 'datetime',
        'tgl_ditolak' => 'datetime',
    ];

    // Relasi ke model Pegawai (pastikan model Pegawai ada)
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
    public function dataPendukung(): MorphMany
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }

}
