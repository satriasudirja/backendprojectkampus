<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegAbsensiRecord extends Model
{
    // Nama tabel
    protected $table = 'simpeg_absensi_record';

    // Karena migration menggunakan bigIncrements (integer)
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';

    // Kolom yang bisa diisi massal
    protected $fillable = [
        'pegawai_id',
        'setting_kehadiran_id',
        'jenis_kehadiran_id',
        'tanggal_absensi',
        'jam_masuk',
        'jam_keluar',
        'terlambat',
        'pulang_awal',
        'check_sum_absensi'
    ];

    // Casting tipe data
    protected $casts = [
        'tanggal_absensi' => 'date',
        'jam_masuk' => 'datetime:H:i:s',
        'jam_keluar' => 'datetime:H:i:s',
        'terlambat' => 'boolean',
        'pulang_awal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relasi ke model Pegawai
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    // Relasi ke model SettingKehadiran
    public function settingKehadiran()
    {
        return $this->belongsTo(SimpegSettingKehadiran::class, 'setting_kehadiran_id');
    }

    // Relasi ke model JenisKehadiran
    public function jenisKehadiran()
    {
        return $this->belongsTo(SimpegJenisKehadiran::class, 'jenis_kehadiran_id');
    }
}