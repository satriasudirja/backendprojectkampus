<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegCutiRecord extends Model
{
    use HasUuids;
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel yang digunakan oleh model.
     *
     * @var string
     */
    protected $table = 'simpeg_cuti_record';

    /**
     * Primary key dari tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Atribut yang dapat diisi secara massal.
     * Kolom seperti 'id', 'created_at', 'updated_at', 'deleted_at'
     * tidak perlu dimasukkan karena dikelola secara otomatis oleh Eloquent.
     *
     * @var array
     */
    protected $fillable = [
        'pegawai_id',
        'jenis_cuti_id',
        'no_urut_cuti',
        'tgl_mulai',
        'tgl_selesai',
        'jumlah_cuti',
        'alasan_cuti',
        'alamat',
        'no_telp',
        'file_cuti',
        'status_pengajuan',
        'tgl_diajukan',
        'tgl_disetujui',
        'disetujui_oleh',
        'tgl_ditolak',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data asli.
     *
     * @var array
     */
    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'jumlah_cuti' => 'integer',
        'tgl_diajukan' => 'datetime',
        'tgl_disetujui' => 'datetime',
        'tgl_ditolak' => 'datetime',
    ];

    // =========================================================
    // RELASI DATABASE
    // =========================================================

    /**
     * Mendapatkan data pegawai yang mengajukan cuti.
     */
    public function pegawai()
    {
        return $this->belongsTo(SimpegPegawai::class, 'pegawai_id');
    }

    /**
     * Mendapatkan data jenis cuti yang diambil.
     */
    public function jenisCuti()
    {
        return $this->belongsTo(SimpegDaftarCuti::class, 'jenis_cuti_id');
    }

    /**
     * Mendapatkan data pegawai yang menyetujui pengajuan.
     */
    public function approver()
    {
        // Relasi ini menghubungkan 'disetujui_oleh' dengan 'id' di tabel pegawai.
        return $this->belongsTo(SimpegPegawai::class, 'disetujui_oleh');
    }

    // =========================================================
    // ACCESSORS & MUTATORS (Opsional, tapi membantu)
    // =========================================================

    /**
     * Accessor untuk mendapatkan URL lengkap dari file cuti.
     *
     * @return string|null
     */
    public function getFileCutiUrlAttribute()
    {
        if ($this->file_cuti) {
            // Sesuaikan path jika berbeda, contoh: 'storage/dokumen/cuti/'
            return asset('storage/dokumen_cuti/' . $this->file_cuti);
        }
        return null;
    }
}
