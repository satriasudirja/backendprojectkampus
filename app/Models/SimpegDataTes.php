<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataTes extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_tes';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_tes_id',
        'nama_tes',
        'penyelenggara',
        'tgl_tes',
        'skor',
        'file_pendukung',
        'tgl_input'
    ];

    protected $casts = [
        'tgl_tes' => 'date',
        'tgl_input' => 'date',
        'skor' => 'float'
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis tes
    public function jenisTes()
    {
        return $this->belongsTo(SimpegDaftarJenisTest::class, 'jenis_tes_id');
    }
}