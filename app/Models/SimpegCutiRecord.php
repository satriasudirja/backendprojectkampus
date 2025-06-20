<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegCutiRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_cuti_record';

    protected $primaryKey = 'id';
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
        'status_pengajuan',
        'created_at',
        'updated_at',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'deleted_at',

    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'jumlah_cuti' => 'integer',
        'tgl_diajukan' => 'date',
        'tgl_disetujui' => 'date',
        'tgl_ditolak'=> 'date',
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