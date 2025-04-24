<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegDaftarJenisTest extends Model
{
    use HasFactory;

    protected $table = 'simpeg_daftar_jenis_test';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string'; // Untuk UUID

    protected $fillable = [
        'id',
        'kode',
        'jenis_test',
        'nilai_min',
        'nilai_max'
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