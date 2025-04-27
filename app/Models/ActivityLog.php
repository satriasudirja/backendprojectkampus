<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'pegawai_id',
        'event',
        'model_type',
        'model_id',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id'); // Assuming you have a Pegawai model
    }
}