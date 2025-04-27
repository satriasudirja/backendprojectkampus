<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterOutputPenelitian extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'simpeg_master_output_penelitian';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'kode',
        'output_penelitian'
    ];
}