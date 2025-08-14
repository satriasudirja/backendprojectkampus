<?php
// File: app/Models/SimpegUser.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class SimpegUser extends Authenticatable implements JWTSubject
{
    use HasUuids; // Tambahkan ini karena Anda menggunakan UUID sebagai primary key

    protected $table = 'simpeg_users';
    
    // Pastikan keyType di-set ke string untuk UUID
    protected $keyType = 'string';
    public $incrementing = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username', 
        'password', 
        'is_active', // Disesuaikan dengan nama kolom di migrasi ('is_active' bukan 'aktif')
        'pegawai_id', // WAJIB ada agar bisa diisi saat membuat user baru
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    /**
     * Memberitahu Laravel untuk menggunakan 'username' untuk login.
     */
    public function username()
    {
        return 'username';
    }
    
    
    /**
     * Mendefinisikan relasi bahwa User ini memiliki satu Role melalui Pegawai.
     */
    public function role(): HasOneThrough
    {
        return $this->hasOneThrough(
            SimpegUserRole::class,
            SimpegPegawai::class,
            'id',           // Foreign key on SimpegPegawai table
            'id',           // Foreign key on SimpegUserRole table
            'pegawai_id',   // Local key on SimpegUser table
            'role_id',      // Local key on SimpegPegawai table
        );
    }

    /**
     * Mendefinisikan relasi bahwa User ini milik satu Pegawai.
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    /**
     * Method untuk mendapatkan data pegawai terkait
     */
    public function getPegawaiData()
    {
        return $this->pegawai;
    }

    // --- JWT Methods ---
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        // Mengambil data dari relasi untuk dimasukkan ke dalam token JWT
        // Pastikan relasi 'pegawai' sudah di-load untuk menghindari error
        $this->loadMissing('pegawai.role');

        return [
            'username' => $this->username,
            'pegawai_id' => $this->pegawai_id,
            // Mengambil role dari data pegawai, bukan dari user lagi
            'role' => $this->pegawai->role->nama ?? null,
            'role_id' => $this->pegawai->role_id ?? null,
        ];
    }
}