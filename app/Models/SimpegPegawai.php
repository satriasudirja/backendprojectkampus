<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimpegPegawai extends Model
{
    use HasUuids;
    
    // Nama tabel
    protected $table = 'simpeg_pegawai';
    
    // Primary key
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    
    // Mass assignable attributes
    protected $fillable = [
        'id',
        'role_id',
        'unit_kerja_id',
        'kode_status_pernikahan',
        'status_aktif_id',
        'jabatan_fungsional_id', // CHANGED: dari jabatan_akademik_id
        'suku_id',
        'nama',
        'nip',
        'nuptk',
        'nidn',
        'gelar_depan',
        'gelar_belakang',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nama_ibu_kandung',
        'no_sk_capeg',
        'tanggal_sk_capeg',
        'golongan_capeg',
        'tmt_capeg',
        'no_sk_pegawai',
        'tanggal_sk_pegawai',
        'alamat_domisili',
        'agama',
        'atas_nama_rekening',
        'golongan_darah',
        'warga_negara',
        'kecamatan',
        'kota',
        'provinsi',
        'kode_pos',
        'no_handphone',
        'no_whatsapp',
        'no_kk',
        'email_pribadi',
        'email_pegawai',
        'no_ktp',
        'jarak_rumah_domisili',
        'npwp',
        'file_sertifikasi_dosen',
        'no_kartu_pensiun',
        'status_kerja',
        'kepemilikan_nohp_utama',
        'alamat_kependudukan',
        'file_ktp',
        'file_kk',
        'no_rekening',
        'cabang_bank',
        'nama_bank',
        'file_rekening',
        'karpeg',
        'file_karpeg',
        'file_npwp',
        'file_bpjs',
        'file_bpjs_ketenagakerjaan',
        'no_bpjs',
        'no_bpjs_ketenagakerjaan',
        'tinggi_badan',
        'berat_badan',
        'file_tanda_tangan',
        'nomor_polisi',
        'jenis_kendaraan',
        'merk_kendaraan', 
        'file_foto',
        'is_admin',
        'modified_by',
        'modified_dt',
    ];

    // Cast attributes
    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_sk_capeg' => 'date',
        'tmt_capeg' => 'date',
        'tanggal_sk_pegawai' => 'date',
        'modified_dt' => 'datetime',
        'is_admin' => 'boolean',
    ];

    // Relations
    public function user()
    {
        return $this->hasOne(SimpegUser::class, 'pegawai_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(SimpegUserRole::class, 'role_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(SimpegUnitKerja::class, 'unit_kerja_id');
    }

    public function statusPernikahan(): BelongsTo
    {
        return $this->belongsTo(SimpegStatusPernikahan::class, 'kode_status_pernikahan');
    }

    public function statusAktif(): BelongsTo
    {
        return $this->belongsTo(SimpegStatusAktif::class, 'status_aktif_id');
    }

    // CHANGED: Relasi ke jabatan fungsional
    public function jabatanFungsional(): BelongsTo
    {
        return $this->belongsTo(SimpegJabatanFungsional::class, 'jabatan_fungsional_id');
    }

    public function suku(): BelongsTo
    {
        return $this->belongsTo(SimpegSuku::class, 'suku_id');
    }
    
    // HasMany relations
    public function absensiRecords(): HasMany
    {
        return $this->hasMany(SimpegAbsensiRecord::class, 'pegawai_id');
    }
    
    public function dataHubunganKerja(): HasMany
    {
        return $this->hasMany(SimpegDataHubunganKerja::class, 'pegawai_id');
    }
    
    public function dataPendidikanFormal(): HasMany
    {
        return $this->hasMany(SimpegDataPendidikanFormal::class, 'pegawai_id');
    }

    public function dataJabatanFungsional(): HasMany
    {
        return $this->hasMany(SimpegDataJabatanFungsional::class, 'pegawai_id');
    }

    public function dataPangkat(): HasMany
    {
        return $this->hasMany(SimpegDataPangkat::class, 'pegawai_id');
    }

    public function dataJabatanAkademik(): HasMany
    {
        return $this->hasMany(SimpegDataJabatanAkademik::class, 'pegawai_id');
    }

    public function dataJabatanStruktural(): HasMany
    {
        return $this->hasMany(SimpegDataJabatanStruktural::class, 'pegawai_id');
    }

    public function riwayatUnitKerja(): HasMany
    {
        return $this->hasMany(SimpegUnitKerja::class, 'pegawai_id');
    }

    public function evaluasiKinerja(): HasMany
    {
        return $this->hasMany(SimpegEvaluasiKinerja::class, 'pegawai_id');
    }

    public function evaluasiSebagaiPenilai(): HasMany
    {
        return $this->hasMany(SimpegEvaluasiKinerja::class, 'penilai_id');
    }

    public function evaluasiSebagaiAtasanPenilai(): HasMany
    {
        return $this->hasMany(SimpegEvaluasiKinerja::class, 'atasan_penilai_id');
    }
    
    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    
    public function getJWTCustomClaims()
    {
        return [];
    }
}