<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataKemampuanBahasa extends Model
{
    use HasUuids;
    use SoftDeletes, HasFactory;

    protected $table = 'simpeg_data_kemampuan_bahasa';
    protected $primaryKey = 'id';

    // DITAMBAHKAN: Konstanta untuk mapping agar konsisten
    public const SKILL_MAP = [
        'Sangat Baik' => 4,
        'Baik' => 3,
        'Cukup' => 2,
        'Kurang' => 1,
    ];

    public const SKILL_MAP_REVERSE = [
        4 => 'Sangat Baik',
        3 => 'Baik',
        2 => 'Cukup',
        1 => 'Kurang',
    ];

    protected $fillable = [
        'pegawai_id',
        'tahun',
        'bahasa_id',
        'nama_lembaga',
        'kemampuan_mendengar',
        'kemampuan_bicara',
        'kemampuan_menulis',
        'file_pendukung',
        'status_pengajuan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'keterangan'
    ];

    protected $casts = [
        'tahun' => 'integer',
        'pegawai_id' => 'integer',
        'bahasa_id' => 'integer',
        'tgl_input' => 'date',
        // Casting ke integer tetap diperlukan
        'kemampuan_mendengar' => 'integer',
        'kemampuan_bicara' => 'integer',
        'kemampuan_menulis' => 'integer',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];
    
    // DITAMBAHKAN: Tambahkan 'nama_bahasa' dan 'file_url' ke appends agar otomatis muncul di JSON
    protected $appends = ['nama_bahasa', 'file_url'];

    protected $attributes = [
        'status_pengajuan' => 'draft'
    ];

    // --- RELASI (Sudah Benar) ---
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id', 'id');
    }

    public function bahasa()
    {
        return $this->belongsTo(SimpegBahasa::class, 'bahasa_id', 'id');
    }

    // --- SCOPES (Perlu Modifikasi) ---
    public function scopeByPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status_pengajuan', $status);
    }

    // MODIFIKASI: Scope search sekarang lebih pintar
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('tahun', 'like', '%'.$search.'%')
              ->orWhere('nama_lembaga', 'like', '%'.$search.'%')
              ->orWhereHas('bahasa', function($q) use ($search) {
                  $q->where('nama_bahasa', 'like', '%'.$search.'%');
              });

            // Cari berdasarkan teks kemampuan, jika cocok, cari integer-nya
            $skillValue = array_search(ucwords(strtolower($search)), self::SKILL_MAP);
            if ($skillValue !== false) {
                $q->orWhere('kemampuan_mendengar', $skillValue)
                  ->orWhere('kemampuan_bicara', $skillValue)
                  ->orWhere('kemampuan_menulis', $skillValue);
            }
        });
    }

    // --- ACCESSORS (Mengubah data dari DB ke Teks untuk ditampilkan) ---

    public function getKemampuanMendengarAttribute($value)
    {
        return self::SKILL_MAP_REVERSE[$value] ?? null;
    }

    public function getKemampuanBicaraAttribute($value)
    {
        return self::SKILL_MAP_REVERSE[$value] ?? null;
    }

    public function getKemampuanMenulisAttribute($value)
    {
        return self::SKILL_MAP_REVERSE[$value] ?? null;
    }

    public function getNamaBahasaAttribute()
    {
        return $this->bahasa ? $this->bahasa->nama_bahasa : '-';
    }

    public function getFileUrlAttribute()
    {
        return $this->file_pendukung ? url('storage/pegawai/kemampuan-bahasa/'.$this->file_pendukung) : null;
    }

    // --- MUTATORS (Mengubah data dari Request ke Angka untuk disimpan) ---

    public function setKemampuanMendengarAttribute($value)
    {
        $this->attributes['kemampuan_mendengar'] = self::SKILL_MAP[$value] ?? null;
    }

    public function setKemampuanBicaraAttribute($value)
    {
        $this->attributes['kemampuan_bicara'] = self::SKILL_MAP[$value] ?? null;
    }

    public function setKemampuanMenulisAttribute($value)
    {
        $this->attributes['kemampuan_menulis'] = self::SKILL_MAP[$value] ?? null;
    }
}