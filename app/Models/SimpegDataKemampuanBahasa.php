<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataKemampuanBahasa extends Model
{
    use SoftDeletes, HasFactory;
    
    protected $table = 'simpeg_data_kemampuan_bahasa';
    protected $primaryKey = 'id';

    protected $fillable = [
        'pegawai_id',
        'tahun',
        'bahasa_id',
        'nama_lembaga',
        'kemampuan_mendengar',
        'kemampuan_bicara',
        'kemampuan_menulis',
        'file_pendukung',
        'status_pengajuan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'keterangan'
    ];

    protected $casts = [
        'tahun' => 'integer',
        'pegawai_id' => 'integer',
        'bahasa_id' => 'integer',
        'tgl_input' => 'date',
            'kemampuan_mendengar' => 'integer',
        'kemampuan_bicara'=> 'integer',
        'kemampuan_menulis'=> 'integer',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status_pengajuan' => 'draft'
    ];

    // Relationship with Pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id', 'id');
    }

    // Relationship with Bahasa
    public function bahasa()
    {
        return $this->belongsTo(SimpegBahasa::class, 'bahasa_id', 'id');
    }

    // Scope untuk filter by pegawai
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    // Scope untuk filter by status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    // Scope untuk search
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('tahun', 'like', '%'.$search.'%')
              ->orWhere('nama_lembaga', 'like', '%'.$search.'%')
              ->orWhere('kemampuan_mendengar', 'like', '%'.$search.'%')
              ->orWhere('kemampuan_bicara', 'like', '%'.$search.'%')
              ->orWhere('kemampuan_menulis', 'like', '%'.$search.'%')
              ->orWhereHas('bahasa', function($q) use ($search) {
                  $q->where('nama_bahasa', 'like', '%'.$search.'%');
              });
        });
    }

    // Accessor untuk nama bahasa
    public function getNamaBahasaAttribute()
    {
        return $this->bahasa ? $this->bahasa->nama_bahasa : '-';
    }

    // Accessor untuk URL file
    public function getFileUrlAttribute()
    {
        return $this->file_pendukung ? 
               url('storage/pegawai/kemampuan-bahasa/'.$this->file_pendukung) : 
               null;
    }
}