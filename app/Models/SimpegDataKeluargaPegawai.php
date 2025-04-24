<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataKeluargaPegawai extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_keluarga_pegawai';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'nama',
        'jenis_kelamin',
        'status_orangtua',
        'tempat_lahir',
        'tgl_lahir',
        'umur',
        'anak_ke',
        'alamat',
        'telepon',
        'tgl_input',
        'pekerjaan',
        'kartu_nikah',
        'file_akte',
        'pekerjaan_anak',
        'nama_pasangan',
        'pasangan_berkerja_dalam_satu_instansi',
        'tempat_nikah',
        'tgl_nikah',
        'no_akta_nikah'
    ];

    protected $casts = [
        'tgl_lahir' => 'date',
        'tgl_input' => 'date',
        'tgl_nikah' => 'date',
        'pasangan_berkerja_dalam_satu_instansi' => 'boolean',
        'umur' => 'integer',
        'anak_ke' => 'integer'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }
}