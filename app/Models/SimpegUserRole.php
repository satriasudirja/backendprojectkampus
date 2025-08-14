<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegUserRole extends Model
{
    use HasUuids;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_users_roles';

    protected $fillable = [
        'nama',
    ];

    /**
     * Relasi ke jabatan akademik
     */
    // public function jabatanAkademik()
    // {
    //     return $this->hasMany(SimpegJabatanAkademik::class, 'role_id');
    // }
    public function pegawai(): HasMany
    {
        return $this->hasMany(SimpegPegawai::class, 'pegawai_id');
    }
}