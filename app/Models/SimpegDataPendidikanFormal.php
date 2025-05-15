<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataPendidikanFormal extends Model
{
    use HasFactory, SoftDeletes;

   protected $table = 'simpeg_data_pendidikan_formal';
    protected $fillable = [
        'pegawai_id',
        'lokasi_studi',
        'jenjang_pendidikan_id',
        'perguruan_tinggi_id',
        'prodi_perguruan_tinggi_id',
        'gelar_akademik_id',
        'bidang_studi',
        'nisn',
        'konsentrasi',
        'tahun_masuk',
        'tanggal_kelulusan',
        'tahun_lulus',
        'nomor_ijazah',
        'tanggal_ijazah',
        'file_ijazah',
        'file_transkrip',
        'nomor_ijazah_negara',
        'gelar_ijazah_negara',
        'tanggal_ijazah_negara',
        'tgl_input',
        'nomor_induk',
        'judul_tugas',
        'letak_gelar',
        'jumlah_semster_ditempuh',
        'jumlah_sks_kelulusan',
        'ipk_kelulusan',
        'status_pengajuan',
        'tanggal_diajukan',
        'tanggal_disetujui',
        'dibuat_oleh'
    ];

    protected $casts = [
        'tanggal_kelulusan' => 'date',
        'tanggal_ijazah' => 'date',
        'tanggal_ijazah_negara' => 'date',
        'tgl_input' => 'date',
        'tanggal_diajukan' => 'date',
        'tanggal_disetujui' => 'date',
        'jumlah_semster_ditempuh' => 'integer',
        'jumlah_sks_kelulusan' => 'integer',
        'ipk_kelulusan' => 'float'
    ];

    protected $dates = [
        'deleted_at',
    ];
    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenjang studi
    public function jenjangStudi()
    {
        return $this->belongsTo(SimpegJenjangPendidikan::class, 'jenjang_pendidikan_id');
    }


 public function perguruanTinggi()
{
    return $this->belongsTo( MasterPerguruanTinggi::class, 'perguruan_tinggi_id');
}

    // Relasi ke program studi
    public function prodiPerguruanTinggi()
    {
        return $this->belongsTo(MasterProdiPerguruanTinggi::class, 'prodi_perguruan_tinggi_id');
    }

    // Relasi ke gelar akademik
    public function gelarAkademik()
    {
        return $this->belongsTo(MasterGelarAkademik::class, 'gelar_akademik_id');
    }
    //   public function pegawai()
    // {
    //     return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    // }
    
    public function jenjangPendidikan()
    {
        return $this->belongsTo(SimpegJenjangPendidikan::class, 'jenjang_pendidikan_id');
    }


}