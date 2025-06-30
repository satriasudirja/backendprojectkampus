<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder; // Import untuk type-hinting scope
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;


class SimpegDataPenghargaanAdm extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_penghargaan'; // Asumsi nama tabelnya ini

    protected $fillable = [
        'pegawai_id',
        'jenis_penghargaan',
        'nama_penghargaan',
        'no_sk',
        'tanggal_sk',
        'tanggal_penghargaan',
        'keterangan',
        'file_penghargaan',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'tgl_ditangguhkan', // <--- HARUS ADA DI DB dan Model
        // 'keterangan_penolakan', // <--- Pastikan ini sudah dihapus di DB dan Model jika tidak digunakan
    ];

    protected $casts = [
        'tanggal_sk' => 'date',
        'tanggal_penghargaan' => 'date',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
        'tgl_ditangguhkan' => 'datetime', // <--- HARUS ADA DI DB dan Model
    ];

    // Relasi ke pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id', 'id');
    }

    // --- SCOPE UNTUK FILTERING ---

    public function dokumenPendukung()
    {
        return $this->morphMany(SimpegDataPendukung::class, 'pendukungable');
    }

    // Accessor untuk mendapatkan URL file pendukung
    public function getFilePendukungUrlAttribute()
    {
        if ($this->file_pendukung) {
            return url('storage/pegawai/tes/dokumen/' . $this->file_pendukung);
        }
        return null;
    }

    // Accessor untuk cek apakah file pendukung exists
    public function getFilePendukungExistsAttribute()
    {
        if ($this->file_pendukung) {
            return Storage::exists('public/pegawai/tes/dokumen/' . $this->file_pendukung);
        }
        return false;
    }
    public function scopeFilterByUnitKerja(Builder $query, $unitKerjaId)
    {
        if (!$unitKerjaId || $unitKerjaId === 'semua') {
            return $query;
        }
        
        // Asumsi getAllChildIdsRecursively ada di SimpegUnitKerja model sebagai static method
        // Pastikan SimpegUnitKerja di-import di sini jika diperlukan
        $unitKerjaTarget = \App\Models\SimpegUnitKerja::find($unitKerjaId); 

        if ($unitKerjaTarget) {
            // Asumsi getAllChildIdsRecursively ada di SimpegUnitKerja model
            $unitIdsInScope = \App\Models\SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
            $unitIdsInScope[] = $unitKerjaTarget->id; 

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

    public function scopeFilterByJenisPenghargaan(Builder $query, $jenisPenghargaan)
    {
        if (!$jenisPenghargaan || $jenisPenghargaan === 'semua') {
            return $query;
        }
        return $query->where('jenis_penghargaan', $jenisPenghargaan);
    }

    public function scopeGlobalSearch(Builder $query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('jenis_penghargaan', 'like', '%' . $search . '%')
              ->orWhere('nama_penghargaan', 'like', '%' . $search . '%')
              ->orWhere('no_sk', 'like', '%' . $search . '%')
              ->orWhere('keterangan', 'like', '%' . $search . '%')
              ->orWhereHas('pegawai', function ($q2) use ($search) {
                  $q2->where('nip', 'like', '%' . $search . '%')
                     ->orWhere('nama', 'like', '%' . $search . '%');
              });
        });
    }
    
    // <--- INI ADALAH SCOPE YANG HILANG DAN PERLU DITAMBAHKAN --->
    public function scopeByStatus(Builder $query, $status)
    {
        if ($status && $status != 'semua') {
            return $query->where('status_pengajuan', $status);
        }
        return $query;
    }
}