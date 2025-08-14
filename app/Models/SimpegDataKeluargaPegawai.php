<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegDataKeluargaPegawai extends Model
{
    use HasUuids;
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'simpeg_data_keluarga_pegawai';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pegawai_id',
        'nama',
        'jenis_kelamin',
        'status_orangtua',
        'tempat_lahir',
        'tgl_lahir',
        'umur',
        'anak_ke',
        'alamat',
        'telepon',
        'tgl_input',
        'pekerjaan',
        'kartu_nikah',
        'file_akte',
        'file_karpeg_pasangan',
        'pekerjaan_anak',
        'nama_pasangan',
        'pasangan_berkerja_dalam_satu_instansi',
        'status_pengajuan',
        'keterangan',
        'tgl_input',
        'tgl_diajukan',
        'tgl_disetujui',
        'tgl_ditolak',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'tgl_lahir' => 'date',
        'tgl_input' => 'date',
        'pasangan_berkerja_dalam_satu_instansi' => 'boolean',
        'umur' => 'integer',
        'anak_ke' => 'integer',
        'pegawai_id' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     */
    protected $dates = [
        'deleted_at',
        'tgl_lahir',
        'tgl_input',
    ];

    /**
     * Get the pegawai that owns the keluarga data.
     */
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    /**
     * Scope a query to only include data for specific pegawai.
     */
    public function scopeForPegawai($query, $pegawaiId)
    {
        return $query->where('pegawai_id', $pegawaiId);
    }

    /**
     * Scope a query to only include children data.
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('anak_ke');
    }

    /**
     * Scope a query to only include spouse data.
     */
    public function scopeSpouse($query)
    {
        return $query->whereNotNull('nama_pasangan');
    }

    /**
     * Scope a query to only include parent data.
     */
    public function scopeParents($query)
    {
        return $query->whereNotNull('status_orangtua');
    }

    /**
     * Get full name with status.
     */
    public function getFullNameWithStatusAttribute()
    {
        $status = $this->status_orangtua ? " ({$this->status_orangtua})" : '';
        return $this->nama . $status;
    }

    /**
     * Get age from birth date.
     */
    public function getCalculatedAgeAttribute()
    {
        if ($this->tgl_lahir) {
            return $this->tgl_lahir->age;
        }
        return $this->umur;
    }

    /**
     * Check if has marriage certificate.
     */
    public function hasMarriageCertificate()
    {
        return !empty($this->kartu_nikah);
    }

    /**
     * Check if has birth certificate.
     */
    public function hasBirthCertificate()
    {
        return !empty($this->file_akte);
    }

    /**
     * Check if this is a child record.
     */
    public function isChild()
    {
        return !is_null($this->anak_ke);
    }

    /**
     * Check if this is a spouse record.
     */
    public function isSpouse()
    {
        return !is_null($this->nama_pasangan);
    }

    /**
     * Check if this is a parent record.
     */
    public function isParent()
    {
        return !is_null($this->status_orangtua);
    }

    /**
     * Get the type of family member.
     */
    public function getFamilyTypeAttribute()
    {
        if ($this->isSpouse()) {
            return 'Pasangan';
        } elseif ($this->isChild()) {
            return 'Anak';
        } elseif ($this->isParent()) {
            return 'Orang Tua';
        }
        return 'Keluarga';
    }
}