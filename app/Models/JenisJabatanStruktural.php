<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisJabatanStruktural extends Model
{
    use SoftDeletes;
    use HasFactory;
    use HasUuids;
    
    protected $table = 'simpeg_jenis_jabatan_struktural';
    protected $primaryKey = 'id';

    protected $fillable = [
        'kode',
        'jenis_jabatan_struktural'
    ];

      public function jabatanStruktural()
    {
        return $this->hasMany(SimpegJabatanStruktural::class, 'jenis_jabatan_struktural_id');
    }
}