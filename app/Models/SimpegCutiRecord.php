<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegCutiRecord extends Model
{
    use HasFactory;

    protected $table = 'simpeg_cuti_record';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Karena menggunakan UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_cuti_id',
        'no_urut_cuti',
        'tgl_mulai',
        'tgl_selesai',
        'jumlah_cuti',
        'alasan_cuti',
        'alamat',
        'no_telp',
        'file_cuti',
        'status_pengajuan'
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_sclesai' => 'date',
        'jumlah_cuti' => 'integer',
    ];

    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    public function jenisCuti()
    {
        return $this->belongsTo(SimpegDaftarCuti::class, 'jenis_cuti_id');
    }
}