<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenggajianKomponenPotongan extends Model
{
    use HasFactory;
    protected $table = 'penggajian_komponen_potongan';
    protected $fillable = ['penggajian_pegawai_id', 'kode_komponen', 'deskripsi', 'nominal'];

    /**
     * Relasi ke slip gaji induk.
     */
    public function penggajianPegawai(): BelongsTo
    {
        return $this->belongsTo(PenggajianPegawai::class, 'penggajian_pegawai_id');
    }
}
