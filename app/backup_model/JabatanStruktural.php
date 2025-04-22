<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JabatanStruktural extends Model
{
    use HasFactory;

    protected $table = 'jabatan_struktural';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'nama_jabatan', 'eselon', 'unit_kerja'];

    public function dataJabatanStruktural()
    {
        return $this->hasMany(DataJabatanStruktural::class);
    }
}
