<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegBank extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'simpeg_bank';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode',
        'nama_bank',
    ];

    /**
     * Relasi one-to-many ke model SimpegPegawai.
     * Satu bank bisa dimiliki oleh banyak pegawai.
     */
    public function pegawai()
    {
        // Pastikan nama foreign key di tabel simpeg_pegawai adalah 'bank_id'
        return $this->hasMany(SimpegPegawai::class, 'bank_id');
    }
}