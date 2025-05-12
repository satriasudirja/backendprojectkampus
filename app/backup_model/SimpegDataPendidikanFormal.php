<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpegDataPendidikanFormal extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_pendidikan_formal';

    protected $fillable = [
        'id', 'pegawai_id', 'jenjang_studi', 'perguruan_tinggi_id',
        'prodi_perguruan_tinggi_id', 'gelar_akademik_id', 'lokasi_studi',
        'nama_institusi', 'nisn', 'konsentrasi', 'tahun_masuk', 'tanggal_kelulusan',
        'tahun_lulus', 'nomor_ijazah', 'tanggal_ijazah', 'file_ijazah',
        'file_transkrip', 'nomor_ijazah_negara', 'tanggal_ijazah_negara',
        'tgl_input', 'nomor_induk', 'judul_tugas', 'letak_gelar',
        'jumlah_semester_ditempuh', 'jumlah_sks_kelulusan', 'ipk_kelulusan'
    ];

    
}
