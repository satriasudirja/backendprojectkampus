<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegUserRole extends Model
{
    use HasFactory;

    protected $table = 'simpeg_users_roles';

    protected $fillable = [
        'nama',
    ];

    /**
     * Relasi ke jabatan akademik
     */
    public function jabatanAkademik()
    {
        return $this->hasMany(JabatanAkademik::class, 'role_id');
    }
}
