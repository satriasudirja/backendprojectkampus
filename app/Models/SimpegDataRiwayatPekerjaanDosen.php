<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataRiwayatPekerjaanDosen extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_data_riwayat_pekerjaan';

    protected $fillable = [
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
        'tgl_input',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'keterangan'
    ];

    protected $casts = [
        'mulai_bekerja' => 'date',
        'selesai_bekerja' => 'date',
        'area_pekerjaan' => 'boolean',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'date',
        'tgl_disetujui' => 'date',
        'tgl_ditolak' => 'date',
    ];

    /**
     * Get the pegawai that owns the riwayat pekerjaan.
     */
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    /**
     * Get all of the dokumen pendukung for the riwayat pekerjaan.
     */
    public function dataPendukung()
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }
}