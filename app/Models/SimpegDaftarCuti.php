<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDaftarCuti extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'simpeg_daftar_cuti';

    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'kode',
        'nama_jenis_cuti',
        'standar_cuti',
        'format_nomor_surat',
        'keterangan'
    ];

    protected $casts = [
        'standar_cuti' => 'integer',
    ];

    // Relasi ke tabel cuti_record jika diperlukan
    public function cutiRecords()
    {
        return $this->hasMany(SimpegCutiRecord::class, 'jenis_cuti_id');
    }
}