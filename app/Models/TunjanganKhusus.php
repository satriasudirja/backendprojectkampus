<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TunjanganKhusus extends Model
{
    protected $table = 'simpeg_gaji_tunjangan_khusus';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pegawai_id', 'komponen_id', 'jumlah',
        'tgl_mulai', 'tgl_selesai', 'keterangan'
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'jumlah' => 'float'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function komponen()
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}
