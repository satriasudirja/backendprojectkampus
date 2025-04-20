<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataKeluargaPegawai extends Model
{
    use HasFactory;

    protected $table = 'data_keluarga_pegawai';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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

    // Casting tipe data
    protected $casts = [
        'pasangan_berkerja_dalam_satu_instansi' => 'boolean',
        'tgl_lahir' => 'date',
        'tgl_nikah' => 'date',
        'tgl_input' => 'date'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}