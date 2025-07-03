<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegPekerjaan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'simpeg_pekerjaan';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode',
        'nama_pekerjaan',
    ];

    /**
     * Relasi ke DataKeluargaPegawai.
     */
    public function dataKeluarga()
    {
        return $this->hasMany(SimpegDataKeluargaPegawai::class, 'pekerjaan', 'nama_pekerjaan'); // Anda perlu menambahkan kolom 'pekerjaan_id'
    }

    /**
     * Relasi ke DataRiwayatPekerjaan.
     */
    public function dataRiwayatPekerjaan()
    {
        return $this->hasMany(SimpegDataRiwayatPekerjaan::class, 'pekerjaan_id'); // Anda perlu menambahkan kolom 'pekerjaan_id'
    }
}