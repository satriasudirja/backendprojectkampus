<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegPegawai extends Model
{
    // Nama tabel (jika tidak mengikuti konvensi Laravel)
    protected $table = 'simpeg_pegawai';

    // Karena primary key-nya bukan 'id' auto-increment
    protected $primaryKey = 'id';
    

    // Mass assignable attributes
    protected $fillable = [
        'id',
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
        'modified_by',
        'modified_dt',
    ];

    // Tanggal yang akan diperlakukan sebagai Carbon instance
    protected $dates = [
        'tanggal_lahir',
        'tanggal_sk_capeg',
        'tmt_capeg',
        'tanggal_sk_pegawai',
        'modified_dt',
    ];

    // Relasi-relasi bisa ditambahkan di bawah sini
    public function user()
    {
        return $this->belongsTo(SimpegJabatanAkademik::class, 'user_id');
    }

    public function unitKerja()
    {
        return $this->belongsTo(SimpegUnitKerja::class, 'unit_kerja_id');
    }

    public function statusPernikahan()
    {
        return $this->belongsTo(SimpegStatusPernikahan::class, 'kode_status_pernikahan');
    }

    public function statusAktif()
    {
        return $this->belongsTo(SimpegStatusAktif::class, 'status_aktif_id');
    }

    public function jabatanAkademik()
    {
        return $this->belongsTo(SimpegJabatanAkademik::class, 'jabatan_akademik_id');
    }

    public function suku()
    {
        return $this->belongsTo(SimpegSuku::class, 'suku_id');
    }

}
