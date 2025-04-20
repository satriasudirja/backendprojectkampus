<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GajiDetail extends Model
{
    use HasFactory;

    protected $table = 'gaji_detail';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'gaji_slip_id',
        'komponen_id',
        'jumlah',
        'keterangan'
    ];

    protected $casts = [
        'jumlah' => 'float'
    ];

    public function gajiSlip()
    {
        return $this->belongsTo(GajiSlip::class, 'gaji_slip_id');
    }

    public function komponen()
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}
