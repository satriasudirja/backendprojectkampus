<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenggajianPeriode extends Model
{
    use HasFactory;
    protected $table = 'penggajian_periode';
    protected $fillable = ['tahun', 'bulan', 'nama_periode', 'status', 'keterangan'];

    /**
     * Relasi ke slip gaji semua pegawai dalam periode ini.
     */
    public function penggajianPegawai(): HasMany
    {
        return $this->hasMany(PenggajianPegawai::class, 'periode_id');
    }
}