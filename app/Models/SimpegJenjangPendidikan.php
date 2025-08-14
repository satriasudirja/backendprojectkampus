<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SimpegJenjangPendidikan extends Model
{
    use HasUuids;
    
    protected $table = 'simpeg_jenjang_pendidikan';
    protected $guarded = [];
    
    public function dataPendidikanFormal()
    {
        return $this->hasMany(SimpegDataPendidikanFormal::class, 'jenjang_pendidikan_id');
    }

    public function unitKerja()
    {
        return $this->hasMany(SimpegUnitKerja::class, 'tk_pendidikan_id');
    }
}