<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterTunjanganTambahan extends Model
{
    use HasFactory;
    protected $table = 'master_tunjangan_tambahan';
    protected $fillable = ['kode_tunjangan', 'nama_tunjangan', 'deskripsi', 'is_active'];
}
