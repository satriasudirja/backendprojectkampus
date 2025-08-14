<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanAkademik extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasUuids;
    
    protected $table = 'simpeg_jabatan_akademik';
    protected $primaryKey = 'id';
    protected $fillable = [
        'kode',
        'jabatan_akademik',
    ];

    // REMOVED: Semua relasi ke pegawai dan jabatan fungsional
    // Jabatan akademik sekarang menjadi entitas terpisah
    
    // Jika masih ada riwayat jabatan akademik terpisah, bisa tetap ada relasi ini
    public function dataJabatanAkademik(): HasMany
    {
        return $this->hasMany(SimpegDataJabatanAkademik::class, 'jabatan_akademik_id');
    }
}