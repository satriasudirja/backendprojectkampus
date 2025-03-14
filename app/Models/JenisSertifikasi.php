<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisSertifikasi extends Model
{
    use HasFactory;

    protected $table = 'jenis_sertifikasi';
    protected $primaryKey = 'kode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode',
        'jenis_sertifikasi',
        'kategorisertifikasi_id',
    ];

    public function kategoriSertifikasi()
    {
        return $this->belongsTo(KategoriSertifikasi::class, 'kategorisertifikasi_id');
    }
}