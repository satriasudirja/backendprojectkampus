<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDataHubunganKerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_data_hubungan_kerja';

    protected $fillable = [
        'id',
        'no_sk',
        'tgl_sk',
        'tgl_awal',
        'tgl_akhir',
        'pejabat_penetap',
        'file_hubungan_kerja',
        'tgl_input',
        'hubungan_kerja_id',
        'status_aktif_id',
        'pegawai_id',
        'is_aktif',           // ✅ Field baru
        'status_pengajuan'    // ✅ Field baru
    ];

    protected $casts = [
        'tgl_sk' => 'date',
        'tgl_awal' => 'date',
        'tgl_akhir' => 'date',
        'tgl_input' => 'date',
        'is_aktif' => 'boolean'  // ✅ Cast boolean
    ];

    // Konstanta untuk status pengajuan
    const STATUS_DRAFT = 'draft';
    const STATUS_DIAJUKAN = 'diajukan';
    const STATUS_DISETUJUI = 'disetujui';
    const STATUS_DITOLAK = 'ditolak';

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenis hubungan kerja
    public function hubunganKerja()
    {
        return $this->belongsTo(HubunganKerja::class, 'hubungan_kerja_id');
    }

    // Relasi ke status aktif
    public function statusAktif()
    {
        return $this->belongsTo(SimpegStatusAktif::class, 'status_aktif_id');
    }

    // Scope untuk data aktif
    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    // Scope berdasarkan status pengajuan
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    // Scope berdasarkan pegawai
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    // Accessor untuk status pengajuan label
    public function getStatusPengajuanLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'diajukan' => 'Diajukan',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak'
        ];

        return $labels[$this->status_pengajuan] ?? 'Unknown';
    }

    // Method untuk mengaktifkan hubungan kerja
    public function activate()
    {
        // Nonaktifkan hubungan kerja lain untuk pegawai yang sama
        static::where('pegawai_id', $this->pegawai_id)
              ->where('id', '!=', $this->id)
              ->update(['is_aktif' => false]);

        // Aktifkan hubungan kerja ini
        $this->update(['is_aktif' => true]);
    }

    // Method untuk menonaktifkan hubungan kerja
    public function deactivate()
    {
        $this->update(['is_aktif' => false]);
    }

    // Method untuk update status pengajuan
    public function updateStatus($status)
    {
        $validStatuses = [
            self::STATUS_DRAFT,
            self::STATUS_DIAJUKAN,
            self::STATUS_DISETUJUI,
            self::STATUS_DITOLAK
        ];

        if (in_array($status, $validStatuses)) {
            $this->update(['status_pengajuan' => $status]);
            return true;
        }

        return false;
    }
}