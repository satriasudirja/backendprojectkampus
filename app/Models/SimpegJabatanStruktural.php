<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanStruktural extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_jabatan_struktural';
    protected $fillable = [
        'unit_kerja_id',
        'jenis_jabatan_struktural_id',
        'pangkat_id',
        'eselon_id',
        'kode',
        'singkatan',
        'alamat_email',
        'beban_sks',
        'is_pimpinan',
        'aktif',
        'keterangan',
        'parent_jabatan',
        'tunjangan',
    ];

    protected $casts = [
        'is_pimpinan' => 'boolean',
        'aktif' => 'boolean',
        'tgl_ditolak' => 'datetime',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
    ];

    public function unitKerja()
    {
        return $this->belongsTo(SimpegUnitKerja::class, 'unit_kerja_id');
    }


    //  public function jenisJabatanStruktural()
    // {
    //     return $this->belongsTo(SimpegJenisJabatanStruktural::class, 'jenis_jabatan_struktural_id');
    // }

    /**
     * Mendapatkan semua data jabatan struktural pegawai dengan jabatan ini.
     */
    public function dataJabatanStruktural()
    {
        return $this->hasMany(SimpegDataJabatanStruktural::class, 'jabatan_fungsional_id');
    }

    public function jenisJabatanStruktural()
    {
        return $this->belongsTo(JenisJabatanStruktural::class, 'jenis_jabatan_struktural_id');
    }

    public function pangkat()
    {
        return $this->belongsTo(SimpegMasterPangkat::class, 'pangkat_id');
    }

    public function eselon()
    {
        return $this->belongsTo(SimpegEselon::class, 'eselon_id');
    }

    public function parent()
    {
        return $this->belongsTo(SimpegJabatanStruktural::class, 'parent_jabatan', 'kode');
    }

    public function children()
    {
        return $this->hasMany(SimpegJabatanStruktural::class, 'parent_jabatan', 'kode');
    }
        public function jabatanStruktural()
    {
        return $this->belongsTo(SimpegJabatanStruktural::class, 'jabatan_fungsional_id');
    }
}