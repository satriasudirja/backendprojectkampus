<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegEvaluasiKinerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_evaluasi_kinerja';
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'pegawai_id',
        'penilai_id',
        'atasan_penilai_id',
        'periode_tahun',
        'tanggal_penilaian',
        'nilai_kehadiran',
        'nilai_pendidikan',
        'nilai_penelitian',
        'nilai_pengabdian',
        'nilai_penunjang1',
        'nilai_penunjang2',
        'nilai_penunjang3',
        'nilai_penunjang4',
        'total_nilai',
        'sebutan_total',
        'tgl_input',
    ];
}

