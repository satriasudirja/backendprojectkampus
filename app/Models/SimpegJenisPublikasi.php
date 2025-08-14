<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisPublikasi extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $table = 'simpeg_jenis_publikasi';
    protected $primaryKey = 'id';

    protected $fillable = [
        'kode',
        'jenis_publikasi',
    ];
}
