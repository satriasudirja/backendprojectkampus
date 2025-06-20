<?php

namespace App\Models; // Corrected: Using backslash \

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder; // Import for type-hinting scopes
use Carbon\Carbon; // Ensure Carbon is imported if you use it for date handling

class SimpegDataPendidikanFormal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_data_pendidikan_formal';
    protected $fillable = [
        'pegawai_id',
        'lokasi_studi',
        'jenjang_pendidikan_id',
        'perguruan_tinggi_id',
        'prodi_perguruan_tinggi_id',
        'gelar_akademik_id',
        'bidang_studi',
        'nisn',
        'konsentrasi',
        'tahun_masuk',
        'tanggal_kelulusan',
        'tahun_lulus',
        'nomor_ijazah',
        'tanggal_ijazah',
        'file_ijazah',
        'file_transkrip',
        'nomor_ijazah_negara',
        'gelar_ijazah_negara',
        'tanggal_ijazah_negara',
        'tgl_input',
        'nomor_induk',
        'judul_tugas',
        'letak_gelar',
        'jumlah_semster_ditempuh',
        'jumlah_sks_kelulusan',
        'ipk_kelulusan',
        'status_pengajuan',
        'tanggal_diajukan',
        'tanggal_disetujui',
        'tanggal_ditolak', // Tambahkan ini
        // 'tanggal_ditangguhkan', // Dihapus
        // 'keterangan_penolakan', // Dihapus
        'dibuat_oleh'
    ];

    protected $casts = [
        'tanggal_kelulusan' => 'date',
        'tanggal_ijazah' => 'date',
        'tanggal_ijazah_negara' => 'date',
        'tgl_input' => 'datetime', // Ubah ke datetime jika menyimpan timestamp
        'tanggal_diajukan' => 'datetime', // Ubah ke datetime
        'tanggal_disetujui' => 'datetime', // Ubah ke datetime
        'tanggal_ditolak' => 'datetime', // Tambahkan ini dan pastikan di DB juga datetime
        // 'tanggal_ditangguhkan' => 'datetime', // Dihapus
        'jumlah_semster_ditempuh' => 'integer',
        'jumlah_sks_kelulusan' => 'integer',
        'ipk_kelulusan' => 'float'
    ];

    protected $dates = [
        'deleted_at',
    ];

    // Relasi ke tabel pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke jenjang pendidikan
    public function jenjangPendidikan() // Menggunakan ini karena di controller sudah ada
    {
        return $this->belongsTo(SimpegJenjangPendidikan::class, 'jenjang_pendidikan_id');
    }

    public function perguruanTinggi()
    {
        return $this->belongsTo(MasterPerguruanTinggi::class, 'perguruan_tinggi_id');
    }

    // Relasi ke program studi
    public function prodiPerguruanTinggi()
    {
        return $this->belongsTo(MasterProdiPerguruanTinggi::class, 'prodi_perguruan_tinggi_id');
    }

    // Relasi ke gelar akademik
    public function gelarAkademik()
    {
        return $this->belongsTo(MasterGelarAkademik::class, 'gelar_akademik_id');
    }

    // Relasi polimorfik ke dokumen pendukung
    public function dokumenPendukung()
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
        
        $unitKerjaTarget = \App\Models\SimpegUnitKerja::find($unitKerjaId); // Corrected: Use \App\Models\SimpegUnitKerja

        if ($unitKerjaTarget) {
            $unitIdsInScope = \App\Models\SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget); // Corrected: Use \App\Models\SimpegUnitKerja
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

    public function scopeFilterByJenjangPendidikan(Builder $query, $jenjangPendidikanId)
    {
        if (!$jenjangPendidikanId || $jenjangPendidikanId === 'semua') {
            return $query;
        }
        return $query->where('jenjang_pendidikan_id', $jenjangPendidikanId);
    }

    public function scopeFilterByPerguruanTinggi(Builder $query, $perguruanTinggiId)
    {
        if (!$perguruanTinggiId || $perguruanTinggiId === 'semua') {
            return $query;
        }
        return $query->where('perguruan_tinggi_id', $perguruanTinggiId);
    }

    public function scopeFilterByProdi(Builder $query, $prodiId)
    {
        if (!$prodiId || $prodiId === 'semua') {
            return $query;
        }
        return $query->where('prodi_perguruan_tinggi_id', $prodiId);
    }

    public function scopeFilterByTahunMasuk(Builder $query, $tahunMasuk)
    {
        if (!$tahunMasuk) {
            return $query;
        }
        return $query->where('tahun_masuk', $tahunMasuk);
    }

    public function scopeFilterByTahunLulus(Builder $query, $tahunLulus)
    {
        if (!$tahunLulus) {
            return $query;
        }
        return $query->where('tahun_lulus', $tahunLulus);
    }

    public function scopeGlobalSearch(Builder $query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('lokasi_studi', 'like', '%' . $search . '%')
              ->orWhere('bidang_studi', 'like', '%' . $search . '%')
              ->orWhere('nisn', 'like', '%' . $search . '%')
              ->orWhere('konsentrasi', 'like', '%' . $search . '%')
              ->orWhere('nomor_ijazah', 'like', '%' . $search . '%')
              ->orWhere('nomor_ijazah_negara', 'like', '%' . $search . '%')
              ->orWhere('judul_tugas', 'like', '%' . $search . '%')
              ->orWhereHas('jenjangPendidikan', function ($jq) use ($search) {
                  $jq->where('jenjang_pendidikan', 'like', '%' . $search . '%');
              })
              ->orWhereHas('perguruanTinggi', function ($jq) use ($search) {
                  $jq->where('nama_universitas', 'like', '%' . $search . '%');
              })
              ->orWhereHas('prodiPerguruanTinggi', function ($jq) use ($search) {
                  $jq->where('nama_prodi', 'like', '%' . $search . '%');
              })
              ->orWhereHas('gelarAkademik', function ($jq) use ($search) {
                  $jq->where('nama_gelar', 'like', '%' . $search . '%')
                     ->orWhere('gelar', 'like', '%' . $search . '%');
              })
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
