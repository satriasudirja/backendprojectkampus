<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegJenisPelanggaran extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_jenis_pelanggaran';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama_pelanggaran',
    ];
}
