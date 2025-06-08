<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJabatanAkademik extends Model
{
    use HasFactory;
    use SoftDeletes;
    
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

    /**
     * Relasi ke model Pegawai (one to many)
     */
    public function pegawai(): HasMany
    {
        return $this->hasMany(SimpegPegawai::class, 'jabatan_akademik_id');
    }

    /**
     * Relasi ke model Jabatan Fungsional melalui jabatan_akademik_id
     * Sesuai dengan penjelasan: simpeg_jabatan_fungsional berelasi dengan jabatan_akademik_id dari simpeg_jabatan_akademik
     */
    public function jabatanFungsional(): HasMany
    {
        return $this->hasMany(SimpegJabatanFungsional::class, 'jabatan_akademik_id');
    }
}