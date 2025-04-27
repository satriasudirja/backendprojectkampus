<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GajiSlip extends Model
{
    use HasFactory;

    protected $table = 'gaji_slip';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'pegawai_id', 'periode', 'total_gaji'];

    public function details()
    {
        return $this->hasMany(GajiDetail::class);
    }
}