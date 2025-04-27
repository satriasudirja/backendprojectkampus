<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class SimpegJabatanAkademik extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan_akademik';
    protected $primaryKey = 'id';
    protected $fillable = [
        'role_id',
        'kode',
        'jabatan_akademik',
    ];

    /**
     * Relasi ke model Role (jika ada model terkait, misalnya UserRole)
     */
    public function role()
    {
        return $this->belongsTo(SimpegUserRole::class, 'role_id');
    }
    public function pegawai(): HasMany
    {
        return $this->hasMany(SimpegPegawai::class, 'jabatan_akademik_id');
    }
}