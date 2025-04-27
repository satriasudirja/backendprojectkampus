<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDaftarJenisTest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'simpeg_daftar_jenis_test';

    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'kode',
        'jenis_tes',
        'nilai_minimum',
        'nilai_maksimum'
    ];

    protected $casts = [
        'nilai_min' => 'float',
        'nilai_max' => 'float'
    ];

    // Relasi ke tabel test records jika diperlukan
    public function testRecords()
    {
        return $this->hasMany(SimpegDataTes::class, 'jenis_test_id');
    }
}