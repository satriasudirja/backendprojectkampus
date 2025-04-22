<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimpegUserRole extends Model
{
    protected $table = 'simpeg_users_roles';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'nama'
    ];
    
      // Role constants untuk memudahkan referensi
      const ADMIN = 1;
      const DOSEN = 2;
      const TENAGA_KEPENDIDIKAN = 3;
      const DOSEN_INDUSTRI = 4;
      
      public function users(): HasMany
      {
          return $this->hasMany(SimpegUser::class, 'role_id');
      }
  
    
}