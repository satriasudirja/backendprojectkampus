<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegJabatanAkademik extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan_akademik';

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
}
