<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpegGajiSlip extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'simpeg_gaji_slip';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'pegawai_id', 'periode', 'total_gaji'];

    public function details()
    {
        return $this->hasMany(GajiDetail::class);
    }
}