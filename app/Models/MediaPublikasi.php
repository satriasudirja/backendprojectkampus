<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaPublikasi extends Model
{
    use HasFactory;
    protected $table = 'media_publikasi';
    protected $fillable = [
        'nama',
    ];
}