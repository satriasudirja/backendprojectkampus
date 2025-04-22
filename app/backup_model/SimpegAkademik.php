<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimpegAkademik extends Model
{
    protected $table = 'simpeg_jabatan_akademik';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'role_id', 'kode', 'jabatan_akademik'
    ];
    
    public function users(): HasMany
    {
        return $this->hasMany(SimpegUser::class, 'role_id');
    }
}

