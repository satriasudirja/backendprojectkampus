<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDaftarJenisTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_daftar_jenis_test';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'kode',
        'jenis_tes',
        'nilai_minimal',  // ✅ Sesuaikan dengan migration
        'nilai_maksimal'  // ✅ Sesuaikan dengan migration
    ];

    protected $casts = [
        'nilai_minimal' => 'float',   // ✅ Sesuaikan dengan fillable
        'nilai_maksimal' => 'float'   // ✅ Sesuaikan dengan fillable
    ];

    // ✅ Relasi ke SimpegDataTes - sesuaikan foreign key
    public function dataTest()
    {
        return $this->hasMany(SimpegDataTes::class, 'jenis_tes_id');
    }

    // Untuk backward compatibility jika ada code lain yang pakai
    public function testRecords()
    {
        return $this->dataTest();
    }

    // Accessor untuk kompatibilitas dengan controller
    public function getNamaJenisTestAttribute()
    {
        return $this->jenis_tes;
    }

    // Accessor untuk range skor
    public function getRangeSkorAttribute()
    {
        if ($this->nilai_minimal && $this->nilai_maksimal) {
            return $this->nilai_minimal . ' - ' . $this->nilai_maksimal;
        } elseif ($this->nilai_maksimal) {
            return '0 - ' . $this->nilai_maksimal;
        }
        return '-';
    }

    // Method untuk validasi skor
    public function isValidSkor($skor)
    {
        if ($this->nilai_minimal && $skor < $this->nilai_minimal) {
            return false;
        }
        
        if ($this->nilai_maksimal && $skor > $this->nilai_maksimal) {
            return false;
        }
        
        return true;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query; // Jika tidak ada kolom is_active
    }
}