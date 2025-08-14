<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataPelanggaran extends Model
{
    use HasUuids;
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_pelanggaran';

    protected $primaryKey = 'id';
    protected $fillable = [
        'id',
        'pegawai_id',
        'jenis_pelanggaran_id',
        'tgl_pelanggaran',
        'no_sk',
        'tgl_sk',
        'keterangan',
        'file_foto',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'tgl_pelanggaran' => 'date',
        'tgl_sk' => 'date',

    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis pelanggaran
    public function jenisPelanggaran()
    {
        return $this->belongsTo(SimpegJenisPelanggaran::class, 'jenis_pelanggaran_id');
    }
    public function scopeFilterByUnitKerja($query, $unitKerjaId)
{
    if ($unitKerjaId && $unitKerjaId !== 'semua') {
        $query->whereHas('pegawai', function($q) use ($unitKerjaId) {
            $q->where('unit_kerja_id', $unitKerjaId);
        });
    }
    
    return $query;
}

/**
 * Scope untuk filter berdasarkan jabatan fungsional
 */
public function scopeFilterByJabatanFungsional($query, $jabatanFungsionalId)
{
    if ($jabatanFungsionalId && $jabatanFungsionalId !== 'semua') {
        $query->whereHas('pegawai.dataJabatanFungsional', function($q) use ($jabatanFungsionalId) {
            $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
        });
    }
    
    return $query;
}

/**
 * Scope untuk filter berdasarkan jenis pelanggaran
 */
public function scopeFilterByJenisPelanggaran($query, $jenisPelanggaranId)
{
    if ($jenisPelanggaranId && $jenisPelanggaranId !== 'semua') {
        $query->where('jenis_pelanggaran_id', $jenisPelanggaranId);
    }
    
    return $query;
}

/**
 * Scope untuk global search (NIP, nama pegawai, jenis pelanggaran)
 */
public function scopeGlobalSearch($query, $search)
{
    if ($search) {
        $query->where(function($q) use ($search) {
            // Search by NIP dan nama pegawai
            $q->whereHas('pegawai', function($pegawaiQuery) use ($search) {
                $pegawaiQuery->where('nip', 'like', '%'.$search.'%')
                           ->orWhere('nama', 'like', '%'.$search.'%');
            })
            // Search by jenis pelanggaran
            ->orWhereHas('jenisPelanggaran', function($jenisQuery) use ($search) {
                $jenisQuery->where('nama_pelanggaran', 'like', '%'.$search.'%');
            })
            // Search by no SK
            ->orWhere('no_sk', 'like', '%'.$search.'%')
            // Search by keterangan
            ->orWhere('keterangan', 'like', '%'.$search.'%');
        });
    }
    
    return $query;
}
}