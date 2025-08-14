<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\gajiSlip;

class SimpegGajiDetail extends Model
{
    use HasUuids;
    use HasFactory;

    protected $table = 'simpeg_gaji_detail';
    protected $primaryKey = 'id';
   
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
        
        return $this->belongsTo(SlipGaji::class, 'gaji_slip_id');
    }

    public function komponen()
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}
