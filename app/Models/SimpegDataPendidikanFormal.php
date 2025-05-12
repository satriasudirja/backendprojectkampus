<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPendidikanFormal extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_pendidikan_formal';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenjang_studi_id',
        'perguruan_tinggi_id',
        'prodi_perguruan_tinggi_id',
        'gelar_akademik_id',
        'lokasi_studi',
        'nama_institusi',
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
        'tanggal_ijazah_negara',
        'tgl_input',
        'nomor_induk',
        'judul_tugas',
        'letak_gelar',
        'jumlah_semester_ditempuh',
        'jumlah_sks_kelulusan',
        'ipk_kelulusan'
    ];

    protected $casts = [
        'lokasi_studi' => 'boolean',
        'tanggal_kelulusan' => 'date',
        'tanggal_ijazah' => 'date',
        'tanggal_ijazah_negara' => 'date',
        'tgl_input' => 'date',
        'jumlah_semester_ditempuh' => 'integer',
        'jumlah_sks_kelulusan' => 'integer',
        'ipk_kelulusan' => 'float'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenjang studi
    public function jenjangStudi()
    {
        return $this->belongsTo(SimpegJenjangStudi::class, 'jenjang_studi_id');
    }

    // Relasi ke perguruan tinggi
    public function perguruanTinggi()
    {
        return $this->belongsTo(SimpegMasterPerguruanTinggi::class, 'perguruan_tinggi_id');
    }

    // Relasi ke program studi
    public function prodiPerguruanTinggi()
    {
        return $this->belongsTo(SimpegMasterProdiPerguruanTinggi::class, 'prodi_perguruan_tinggi_id');
    }

    // Relasi ke gelar akademik
    public function gelarAkademik()
    {
        return $this->belongsTo(SimpegMasterGelarAkademik::class, 'gelar_akademik_id');
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