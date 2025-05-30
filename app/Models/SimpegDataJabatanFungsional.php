<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataJabatanFungsional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_jabatan_fungsional';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'jabatan_fungsional_id',
        'pegawai_id',
        'tmt_jabatan',
        'pejabat_penetap',
        'no_sk',
        'tanggal_sk',
        'file_sk_jabatan',
        'tgl_input',
        'status_pengajuan'
    ];

    protected $casts = [
        'tmt_jabatan' => 'date',
        'tanggal_sk' => 'date',
        'tgl_input' => 'date'
    ];

    // Relationship to Pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relationship to Jabatan Fungsional
    public function jabatanFungsional()
    {
        return $this->belongsTo(SimpegJabatanFungsional::class, 'jabatan_fungsional_id');
    }
}