<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SimpegStatusAktif extends Model
{
    use HasUuids;
    use HasFactory;
    use SoftDeletes;
    protected $table = 'simpeg_status_aktif';
    
    protected $fillable = [
        'kode',
        'nama_status_aktif',
        'status_keluar',
    ];

    protected $casts = [
        'status_keluar' => 'boolean',
    ];

      public function pegawai()
    {
        return $this->hasMany(SimpegPegawai::class, 'status_aktif_id');
    }
}
