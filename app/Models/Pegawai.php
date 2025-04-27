<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pegawai extends Model
{
    protected $table = 'simpeg_pegawai';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        // Semua kolom yang bisa diisi massal
        'user_id',
        'unit_kerja_id',
        'kode_status_pernikahan',
        'status_aktif_id',
        'jabatan_akademik_id',
        'suku_id',
        'nama',
        'nip',
        'password',
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
        'golongan_darah',
        'kota',
        'provinsi',
        'kode_pos',
        'no_telepon_domisili_kontak',
        'no_handphone',
        'no_telephone_kantor',
        'no_kk',
        'email_pribadi',
        'no_ktp',
        'jarak_rumah_domisili',
        'npwp',
        'no_kartu_bpjs',
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
        'no_bpjs_pensiun',
        'file_bpjs_pensiun',
        'tinggi_badan',
        'berat_badan',
        'file_tanda_tangan',
        'modified_by'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_sk_capeg' => 'date',
        'tmt_capeg' => 'date',
        'tanggal_sk_pegawai' => 'date',
        'jarak_rumah_domisili' => 'float',
        'tinggi_badan' => 'integer',
        'berat_badan' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
            // Hash password jika diisi
            if ($model->password && !\Hash::needsRehash($model->password)) {
                $model->password = bcrypt($model->password);
            }
        });

        static::updating(function ($model) {
            // Hash password jika diubah
            if ($model->isDirty('password')) {
                $model->password = bcrypt($model->password);
            }
        });
    }

    // Relasi ke tabel user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke unit kerja
    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    // Relasi ke status pernikahan
    public function statusPernikahan()
    {
        return $this->belongsTo(StatusPernikahan::class, 'kode_status_pernikahan');
    }

    // Relasi ke status aktif
    public function statusAktif()
    {
        return $this->belongsTo(StatusAktif::class, 'status_aktif_id');
    }

    // Relasi ke jabatan akademik
    public function jabatanAkademik()
    {
        return $this->belongsTo(JabatanAkademik::class, 'jabatan_akademik_id');
    }

    // Relasi ke suku
    public function suku()
    {
        return $this->belongsTo(Suku::class, 'suku_id');
    }

    // Accessor untuk file
    public function getFileKtpUrlAttribute()
    {
        return $this->file_ktp ? asset('storage/'.$this->file_ktp) : null;
    }

    // ... tambahkan accessor untuk file lainnya sesuai kebutuhan
}