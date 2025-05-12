<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpegJenjangPendidikan extends Model
{
    protected $table = 'simpeg_jenjang_pendidikan';
    protected $guarded = [];
    
    public function dataPendidikanFormal()
    {
        return $this->hasMany(SimpegDataPendidikanFormal::class, 'jenjang_pendidikan_id');
    }
}