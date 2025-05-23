<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnggotaProfesi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'anggota_profesi';

    protected $fillable = [
        'nama_organisasi',
        'peran_kedudukan',
        'waktu_keanggotaan',
        'tanggal_sinkron',
        'status_pengajuan',
    ];

    protected $casts = [
        'tanggal_sinkron' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public static function getStatusOptions()
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
        ];
    }

    // Scope untuk filter berdasarkan status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    // Scope untuk data yang sudah disetujui
    public function scopeApproved($query)
    {
        return $query->where('status_pengajuan', self::STATUS_APPROVED);
    }

    // Scope untuk pencarian
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nama_organisasi', 'like', "%{$term}%")
              ->orWhere('peran_kedudukan', 'like', "%{$term}%")
              ->orWhere('waktu_keanggotaan', 'like', "%{$term}%");
        });
    }
}