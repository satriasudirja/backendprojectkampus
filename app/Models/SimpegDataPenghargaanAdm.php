<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataPenghargaanAdm extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_penghargaan';

    protected $primaryKey = 'id';
    protected $fillable = [
        'pegawai_id',
        'jenis_penghargaan',
        'nama_penghargaan',
        'no_sk',
        'tanggal_sk',
        'tanggal_penghargaan',
        'keterangan'
    ];

    protected $casts = [
        'tanggal_sk' => 'date',
        'tanggal_penghargaan' => 'date'
    ];

    // Relasi ke tabel pegawai dengan eager loading untuk optimasi
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Scope untuk filter berdasarkan unit kerja
    public function scopeFilterByUnitKerja($query, $unitKerjaId)
    {
        if ($unitKerjaId && $unitKerjaId != 'semua') {
            return $query->whereHas('pegawai.unitKerja', function($q) use ($unitKerjaId) {
                $q->where('kode_unit', $unitKerjaId);
            });
        }
        return $query;
    }

    // Scope untuk filter berdasarkan jabatan fungsional
    public function scopeFilterByJabatanFungsional($query, $jabatanFungsionalId)
    {
        if ($jabatanFungsionalId && $jabatanFungsionalId != 'semua') {
            return $query->whereHas('pegawai.jabatanAkademik.jabatanFungsional', function($q) use ($jabatanFungsionalId) {
                $q->where('id', $jabatanFungsionalId);
            });
        }
        return $query;
    }

    // Scope untuk filter berdasarkan jenis penghargaan
    public function scopeFilterByJenisPenghargaan($query, $jenisPenghargaan)
    {
        if ($jenisPenghargaan && $jenisPenghargaan != 'semua') {
            return $query->where('jenis_penghargaan', $jenisPenghargaan);
        }
        return $query;
    }

    // Scope untuk pencarian global
    public function scopeGlobalSearch($query, $search)
    {
        if ($search) {
            return $query->where(function($q) use ($search) {
                $q->where('jenis_penghargaan', 'like', '%'.$search.'%')
                  ->orWhere('nama_penghargaan', 'like', '%'.$search.'%')
                  ->orWhere('no_sk', 'like', '%'.$search.'%')
                  ->orWhere('tanggal_sk', 'like', '%'.$search.'%')
                  ->orWhere('tanggal_penghargaan', 'like', '%'.$search.'%')
                  ->orWhere('keterangan', 'like', '%'.$search.'%')
                  ->orWhereHas('pegawai', function($query) use ($search) {
                      $query->where('nip', 'like', '%'.$search.'%')
                            ->orWhere('nama', 'like', '%'.$search.'%');
                  });
            });
        }
        return $query;
    }
}