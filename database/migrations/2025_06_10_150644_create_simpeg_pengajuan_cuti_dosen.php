<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simpeg_pengajuan_cuti_dosen', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key ke tabel pegawai
            // Pastikan Anda memiliki tabel 'simpeg_pegawais' dari model SimpegPegawai
            $table->foreignUuid('pegawai_id')
                  ->nullable() // Jadikan kolom ini opsional (bisa NULL)
                  ->constrained('simpeg_pegawai') // Tambahkan constraint ke tabel simpeg_pegawai
                  ->onDelete('cascade'); // Aksi saat data pegawai dihapus

            $table->integer('no_urut_cuti')->unsigned();
            $table->string('jenis_cuti');
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->integer('jumlah_cuti');
            $table->text('alasan_cuti'); // Menggunakan text untuk alasan yang lebih panjang
            $table->text('alamat_selama_cuti'); // Menggunakan text untuk alamat
            $table->string('no_telp', 25);
            
            // Kolom untuk nama file, bisa kosong
            $table->string('file_cuti')->nullable(); 

            // Status dengan nilai default 'draft'
            $table->string('status_pengajuan')->default('draft'); 

            // Kolom tanggal yang bisa kosong (nullable) karena diisi berdasarkan aksi
            $table->date('tgl_input')->nullable();
            $table->date('tgl_diajukan')->nullable();
            $table->date('tgl_disetujui')->nullable();
            $table->date('tgl_ditolak')->nullable();

            // Keterangan dari atasan, bisa kosong
            $table->text('keterangan')->nullable(); 
            
            $table->timestamps(); // Membuat kolom created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_pengajuan_cuti_dosen');
    }
};