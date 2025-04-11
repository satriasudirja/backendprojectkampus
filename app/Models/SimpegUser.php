<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpegUser extends Authenticatable implements JWTSubject
{
    protected $table = 'simpeg_users';
    
    protected $fillable = [
        'username', 
        'password', 
        'aktif', 
        'role_id',
    ];
    
    protected $hidden = [
        'password',
        'remember_token'
    ];
    
    public function username()
    {
        return 'username';
    }
    
    public function role(): BelongsTo
    {
        return $this->belongsTo(SimpegUserRole::class, 'role_id');
    }

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role_id' => $this->role_id,
            'username' => $this->username
        ];
    }
}