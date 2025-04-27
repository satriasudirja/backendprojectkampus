<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegIzinRecord extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_izin_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pegawai_id',
        'jenis_izin_id',
        'alasan',
        'tgl_mulai',
        'tgl_selesai',
        'jumlah_izin',
        'file_pendukung',
        'status_pengajuan',
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisIzin()
    {
        return $this->belongsTo(JenisIzin::class, 'jenis_izin_id');
    }
}
