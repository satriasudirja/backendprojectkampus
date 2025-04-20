<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IzinRecord extends Model
{
    protected $table = 'simpeg_izin_record';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pegawai_id', 'jenis_izin_id', 'alasan', 'tgl_mulai', 
        'tgl_selesai', 'jumlah_izin', 'file_pendukung', 'status_pengajuan'
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jenisIzin()
    {
        return $this->belongsTo(JenisIzin::class);
    }
}
