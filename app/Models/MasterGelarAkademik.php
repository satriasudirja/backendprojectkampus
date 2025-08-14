<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGelarAkademik extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'simpeg_master_gelar_akademik';
    protected $fillable = [
        'id',
        'gelar',
        'nama_gelar'
    ];
}
