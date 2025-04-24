<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegEvaluasiKinerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_evaluasi_kinerja';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'penilai_id',
        'atasan_penilai_id',
        'jenis_kinerja',
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
        'tgl_input'
    ];

    protected $casts = [
        'tanggal_penilaian' => 'date',
        'tgl_input' => 'date',
        'nilai_kehadiran' => 'float',
        'nilai_pendidikan' => 'float',
        'nilai_penelitian' => 'float',
        'nilai_pengabdian' => 'float',
        'nilai_penunjang1' => 'float',
        'nilai_penunjang2' => 'float',
        'nilai_penunjang3' => 'float',
        'nilai_penunjang4' => 'float',
        'total_nilai' => 'float'
    ];

    // Relasi ke pegawai yang dinilai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke penilai
    public function penilai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'penilai_id');
    }

    // Relasi ke atasan penilai
    public function atasanPenilai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'atasan_penilai_id');
    }
}