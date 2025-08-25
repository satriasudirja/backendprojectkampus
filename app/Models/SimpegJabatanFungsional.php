<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanFungsional extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_jabatan_fungsional';
    protected $primaryKey = 'id';

    protected $fillable = [
        'jabatan_akademik_id',
        'pangkat_id',
        'kode',
        'nama_jabatan_fungsional',
        'pangkat',
        'angka_kredit',
        'usia_pensiun',
        'keterangan',
        'tunjangan',
    ];

    // Relasi ke pangkat
    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(SimpegMasterPangkat::class, 'pangkat_id');
    }
    
    // ADDED: Relasi ke pegawai yang menggunakan jabatan fungsional ini
    public function pegawai(): HasMany
    {
        return $this->hasMany(SimpegPegawai::class, 'jabatan_fungsional_id');
    }
    
    // ADDED: Relasi ke riwayat jabatan fungsional pegawai
    public function dataJabatanFungsional(): HasMany
    {
        return $this->hasMany(SimpegDataJabatanFungsional::class, 'jabatan_fungsional_id');
    }
}