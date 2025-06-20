<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder; 
use Illuminate\Support\Facades\Validator;// Import for type-hinting scopes
use Carbon\Carbon; // Ensure Carbon is imported if you use it for date handling

class SimpegDataRiwayatPekerjaanDosen extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_data_riwayat_pekerjaan';

    protected $fillable = [
        'pegawai_id',
        'bidang_usaha',
        'jenis_pekerjaan',
        'jabatan',
        'instansi',
        'divisi',
        'deskripsi',
        'mulai_bekerja',
        'selesai_bekerja',
        'area_pekerjaan',
        'tgl_input',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        // 'tgl_ditangguhkan', // <-- Dihapus
        // 'keterangan_penolakan', // <-- Dihapus
        'keterangan' // Ini sudah ada di field aslinya
    ];

    protected $casts = [
        'mulai_bekerja' => 'date',
        'selesai_bekerja' => 'date',
        'area_pekerjaan' => 'boolean',
        'tgl_input' => 'datetime', // Menggunakan datetime untuk konsistensi timestamp
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
        // 'tgl_ditangguhkan' => 'datetime', // <-- Dihapus
    ];

    /**
     * Get the pegawai that owns the riwayat pekerjaan.
     */
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    /**
     * Get all of the dokumen pendukung for the riwayat pekerjaan.
     */
    public function dataPendukung()
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }

    // --- SCOPE UNTUK FILTERING ---

    public function scopeFilterByPegawai(Builder $query, $pegawaiId)
    {
        if (!$pegawaiId || $pegawaiId === 'semua') {
            return $query;
        }
        return $query->where('pegawai_id', $pegawaiId);
    }

    public function scopeFilterByUnitKerja(Builder $query, $unitKerjaId)
    {
        if (!$unitKerjaId || $unitKerjaId === 'semua') {
            return $query;
        }
        
        $unitKerjaTarget = \App\Models\SimpegUnitKerja::find($unitKerjaId);

        if ($unitKerjaTarget) {
            $unitIdsInScope = \App\Models\SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
            return $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                $q->whereIn('unit_kerja_id', $unitIdsInScope);
            });
        }
        return $query;
    }

    public function scopeFilterByJabatanFungsional(Builder $query, $jabatanFungsionalId)
    {
        if (!$jabatanFungsionalId || $jabatanFungsionalId === 'semua') {
            return $query;
        }

        return $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
            $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
        });
    }

    public function scopeFilterByInstansi(Builder $query, $instansi)
    {
        if (!$instansi || $instansi === 'semua') {
            return $query;
        }
        return $query->where('instansi', 'like', '%' . $instansi . '%');
    }

    public function scopeFilterByJenisPekerjaan(Builder $query, $jenisPekerjaan)
    {
        if (!$jenisPekerjaan || $jenisPekerjaan === 'semua') {
            return $query;
        }
        return $query->where('jenis_pekerjaan', 'like', '%' . $jenisPekerjaan . '%');
    }

    public function scopeFilterByJabatan(Builder $query, $jabatan)
    {
        if (!$jabatan || $jabatan === 'semua') {
            return $query;
        }
        return $query->where('jabatan', 'like', '%' . $jabatan . '%');
    }

    public function scopeFilterByBidangUsaha(Builder $query, $bidangUsaha)
    {
        if (!$bidangUsaha || $bidangUsaha === 'semua') {
            return $query;
        }
        return $query->where('bidang_usaha', 'like', '%' . $bidangUsaha . '%');
    }

    public function scopeFilterByAreaPekerjaan(Builder $query, $areaPekerjaan)
    {
        if ($areaPekerjaan === null || $areaPekerjaan === 'semua') { // Cek null juga
            return $query;
        }
        // Konversi string 'true'/'false' atau 1/0 ke boolean
        return $query->where('area_pekerjaan', filter_var($areaPekerjaan, FILTER_VALIDATE_BOOLEAN));
    }

    public function scopeFilterByMulaiBekerja(Builder $query, $mulaiBekerja)
    {
        if (!$mulaiBekerja) {
            return $query;
        }
        return $query->whereDate('mulai_bekerja', '>=', $mulaiBekerja);
    }

    public function scopeFilterBySelesaiBekerja(Builder $query, $selesaiBekerja)
    {
        if (!$selesaiBekerja) {
            return $query;
        }
        return $query->whereDate('selesai_bekerja', '<=', $selesaiBekerja);
    }

    public function scopeGlobalSearch(Builder $query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('bidang_usaha', 'like', '%' . $search . '%')
              ->orWhere('jenis_pekerjaan', 'like', '%' . $search . '%')
              ->orWhere('jabatan', 'like', '%' . $search . '%')
              ->orWhere('instansi', 'like', '%' . $search . '%')
              ->orWhere('divisi', 'like', '%' . $search . '%')
              ->orWhere('deskripsi', 'like', '%' . $search . '%')
              ->orWhereHas('pegawai', function ($q2) use ($search) {
                  $q2->where('nip', 'like', '%' . $search . '%')
                     ->orWhere('nama', 'like', '%' . $search . '%');
              });
        });
    }
    
    public function scopeByStatus(Builder $query, $status)
    {
        if ($status && $status != 'semua') {
            return $query->where('status_pengajuan', $status);
        }
        return $query;
    }
}
