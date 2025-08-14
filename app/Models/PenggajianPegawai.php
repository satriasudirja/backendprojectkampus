<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenggajianPegawai extends Model
{
    use HasFactory;
    use HasUuids;


    protected $table = 'penggajian_pegawai';
    protected $fillable = ['periode_id', 'pegawai_id', 'total_pendapatan', 'total_potongan', 'gaji_bersih'];

    /**
     * Relasi ke periode penggajian.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PenggajianPeriode::class, 'periode_id');
    }

    /**
     * Relasi ke data pegawai.
     */
    public function pegawai(): BelongsTo
    {
        // Pastikan model SimpegPegawai ada di namespace App\Models
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    /**
     * Relasi ke rincian komponen pendapatan.
     */
    public function komponenPendapatan(): HasMany
    {
        return $this->hasMany(PenggajianKomponenPendapatan::class, 'penggajian_pegawai_id');
    }

    /**
     * Relasi ke rincian komponen potongan.
     */
    public function komponenPotongan(): HasMany
    {
        return $this->hasMany(PenggajianKomponenPotongan::class, 'penggajian_pegawai_id');
    }
}
