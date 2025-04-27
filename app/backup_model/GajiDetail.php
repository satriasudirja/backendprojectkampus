<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GajiDetail extends Model
{
    protected $table = 'simpeg_gaji_detail';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'gaji_slip_id',
        'komponen_id',
        'jumlah',
        'keterangan'
    ];

    protected $casts = [
        'jumlah' => 'float'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    // Relasi ke slip gaji
    public function slipGaji()
    {
        return $this->belongsTo(GajiSlip::class, 'gaji_slip_id');
    }

    // Relasi ke komponen gaji
    public function komponen()
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}