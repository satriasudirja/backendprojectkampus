<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPotonganWajib extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'master_potongan_wajib';

    protected $fillable = [
        'kode_potongan',
        'nama_potongan',
        'jenis_potongan',
        'nilai_potongan',
        'dihitung_dari',
        'is_active',
        'keterangan'
    ];

    protected $casts = [
        'nilai_potongan' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Scope untuk mendapatkan hanya potongan yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Hitung nominal potongan berdasarkan basis perhitungan
     */
    public function hitungPotongan(float $gajiPokok, float $penghasilanBruto): float
    {
        $basis = $this->dihitung_dari === 'gaji_pokok' ? $gajiPokok : $penghasilanBruto;

        if ($this->jenis_potongan === 'persen') {
            return $basis * ($this->nilai_potongan / 100);
        }

        return $this->nilai_potongan;
    }
}