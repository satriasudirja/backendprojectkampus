<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SimpegGajiPeriode extends Model
{
    use HasUuids;
    protected $table = 'simpeg_gaji_periode';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nama_periode',
        'tgl_mulai',
        'tgl_selesai',
        'status'
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date'
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
        return $this->hasMany(slipGaji::class, 'periode_id');
    }

    // Scope untuk periode aktif
    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    // Scope untuk periode draft
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // Scope untuk periode selesai
    public function scopeSelesai($query)
    {
        return $query->where('status', 'selesai');
    }
}