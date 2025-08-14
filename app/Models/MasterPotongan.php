<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPotongan extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'master_potongan';
    protected $fillable = ['kode_potongan', 'nama_potongan', 'deskripsi', 'is_active'];
}
