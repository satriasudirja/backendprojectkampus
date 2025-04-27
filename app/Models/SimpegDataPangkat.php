<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPangkat extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_pangkat';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_sk_id',
        'jenis_kenaikan_pangkat_id',
        'pangkat_id',
        'tmt_pangkat',
        'no_sk',
        'tgl_sk',
        'pejabat_penetap',
        'masa_kerja_tahun',
        'masa_kerja_bulan',
        'acuan_masa_kerja',
        'file_pangkat',
        'tgl_input',
        'status_pengajuan',
        'is_aktif'
    ];

    protected $casts = [
        'tmt_pangkat' => 'date',
        'tgl_sk' => 'date',
        'tgl_input' => 'date',
        'acuan_masa_kerja' => 'boolean',
        'is_aktif' => 'boolean',
        'masa_kerja_tahun' => 'integer',
        'masa_kerja_bulan' => 'integer'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis SK
    public function jenisSk()
    {
        return $this->belongsTo(SimpegDaftarJenisSk::class, 'jenis_sk_id');
    }

    // Relasi ke jenis kenaikan pangkat
    public function jenisKenaikanPangkat()
    {
        return $this->belongsTo(SimpegJenisKenaikanPangkat::class, 'jenis_kenaikan_pangkat_id');
    }

    // Relasi ke pangkat
    public function pangkat()
    {
        return $this->belongsTo(SimpegMasterPangkat::class, 'pangkat_id');
    }
}