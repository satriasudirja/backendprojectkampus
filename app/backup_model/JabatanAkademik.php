<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JabatanAkademik extends Model
{
    use HasFactory;

    protected $table = 'jabatan_akademik';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'nama_jabatan', 'singkatan'];

    // public function dataJabatanAkademik()
    // {
    //     return $this->hasMany(DataJabatanAkademik::class);
    // }
     public function dataJabatanAkademik()
    {
        return $this->hasMany(SimpegDataJabatanAkademik::class, 'jabatan_akademik_id');
    }
    
    public function jabatanFungsional()
    {
        return $this->hasMany(SimpegJabatanFungsional::class, 'jabatan_akademik_id');
    }
}