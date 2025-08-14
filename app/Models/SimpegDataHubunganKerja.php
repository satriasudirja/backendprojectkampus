<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; // Import Builder for type-hinting scopes
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SimpegDataHubunganKerja extends Model
{
    use HasUuids;
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
        'is_aktif',
        'status_pengajuan',
        // Add specific timestamp fields if you want them explicitly fillable/cast
        'tgl_diajukan', // Assuming this field exists based on controller logic
        'tgl_disetujui',
        'tgl_ditolak',
    ];

    protected $casts = [
        'tgl_sk' => 'date',
        'tgl_awal' => 'date',
        'tgl_akhir' => 'date',
        'tgl_input' => 'date',
        'tgl_diajukan' => 'datetime', // Added cast for explicit handling
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
        'is_aktif' => 'boolean'
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
    public function scopeByStatus(Builder $query, $status)
    {
        if ($status && $status !== 'semua') {
            return $query->where('status_pengajuan', $status);
        }
        return $query;
    }

    // Scope berdasarkan pegawai
    public function scopeByPegawai(Builder $query, $pegawaiId)
    {
        if ($pegawaiId && $pegawaiId !== 'semua') {
            return $query->where('pegawai_id', $pegawaiId);
        }
        return $query;
    }

    // NEW SCOPES FOR FILTERING
    public function scopeFilterByUnitKerja(Builder $query, $unitKerjaId)
    {
        if ($unitKerjaId && $unitKerjaId !== 'semua') {
            return $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                // Assuming SimpegUnitKerja has a recursive method or direct children
                // For simplicity, directly checking unit_kerja_id on pegawai.
                // If nested unit logic is needed, implement getAllChildIdsRecursively similar to the PendidikanFormal controller.
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }
        return $query;
    }

    public function scopeFilterByJabatanFungsional(Builder $query, $jabatanFungsionalId)
    {
        if ($jabatanFungsionalId && $jabatanFungsionalId !== 'semua') {
            return $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }
        return $query;
    }

    public function scopeFilterByNipNamaPegawai(Builder $query, $search)
    {
        if ($search) {
            return $query->whereHas('pegawai', function ($q) use ($search) {
                $q->where('nip', 'ilike', '%' . $search . '%') // Use 'ilike' for case-insensitive search in PostgreSQL
                  ->orWhere('nama', 'ilike', '%' . $search . '%');
            });
        }
        return $query;
    }

    public function scopeFilterByTglMulai(Builder $query, $tglMulai)
    {
        if ($tglMulai) {
            return $query->whereDate('tgl_awal', '>=', $tglMulai);
        }
        return $query;
    }

    public function scopeFilterByTglSelesai(Builder $query, $tglSelesai)
    {
        if ($tglSelesai) {
            return $query->whereDate('tgl_akhir', '<=', $tglSelesai);
        }
        return $query;
    }

    public function scopeFilterByHubunganKerjaId(Builder $query, $hubunganKerjaId)
    {
        if ($hubunganKerjaId && $hubunganKerjaId !== 'semua') {
            return $query->where('hubungan_kerja_id', $hubunganKerjaId);
        }
        return $query;
    }

    public function scopeFilterByTglDisetujui(Builder $query, $tglDisetujui)
    {
        if ($tglDisetujui) {
            return $query->whereDate('tgl_disetujui', $tglDisetujui);
        }
        return $query;
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