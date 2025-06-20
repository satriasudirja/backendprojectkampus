<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegEvaluasiKinerja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_evaluasi_kinerja';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pegawai_id',
        'penilai_id',
        'atasan_penilai_id',
        'jenis_kinerja',
        'periode_tahun',
        'tanggal_penilaian',
        'nilai_kehadiran',
        // Kolom Dosen
        'nilai_pendidikan',
        'nilai_penelitian',
        'nilai_pengabdian',
        // Kolom Tendik
        'nilai_komitmen_disiplin',
        'nilai_kepemimpinan_kerjasama',
        'nilai_inisiatif_integritas',
        // Kolom Penunjang (jika masih relevan)
        'nilai_penunjang1',
        'nilai_penunjang2',
        'nilai_penunjang3',
        'nilai_penunjang4',
        'total_nilai',
        'sebutan_total',
        'tgl_input',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal_penilaian' => 'date',
        'tgl_input' => 'date',
        'nilai_kehadiran' => 'float',
        'nilai_pendidikan' => 'float',
        'nilai_penelitian' => 'float',
        'nilai_pengabdian' => 'float',
        'nilai_komitmen_disiplin' => 'float',
        'nilai_kepemimpinan_kerjasama' => 'float',
        'nilai_inisiatif_integritas' => 'float',
        'nilai_penunjang1' => 'float',
        'nilai_penunjang2' => 'float',
        'nilai_penunjang3' => 'float',
        'nilai_penunjang4' => 'float',
        'total_nilai' => 'float',
    ];

    // Relasi-relasi
    public function pegawai() { return $this->belongsTo(SimpegPegawai::class, 'pegawai_id'); }
    public function penilai() { return $this->belongsTo(SimpegPegawai::class, 'penilai_id'); }
    public function atasanPenilai() { return $this->belongsTo(SimpegPegawai::class, 'atasan_penilai_id'); }
}
