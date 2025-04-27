<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlipGaji extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_slip';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pegawai_id',
        'periode_id',
        'total_pendapatan',
        'total_potongan',
        'gaji_bersih',
        'status',
        'tgl_proses'
    ];

    protected $casts = [
        'tgl_proses' => 'date',
        'total_pendapatan' => 'float',
        'total_potongan' => 'float',
        'gaji_bersih' => 'float'
    ];

    // Relasi ke pegawai
    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    // Relasi ke periode
    public function periode()
    {
        return $this->belongsTo(PeriodeGaji::class, 'periode_id');
    }

    // Relasi ke detail komponen gaji (jika ada tabel detail)
    public function detailKomponen()
    {
        return $this->hasMany(DetailKomponenGaji::class, 'slip_gaji_id');
    }
}