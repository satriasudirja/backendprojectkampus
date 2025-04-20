<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataTes extends Model
{
    use HasFactory;

    protected $table = 'data_tes';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisTes()
    {
        return $this->belongsTo(JenisTes::class, 'jenis_tes_id');
    }
}
