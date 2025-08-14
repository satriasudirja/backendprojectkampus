<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::create('simpeg_data_organisasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pegawai_id');
            
            // Informasi Dasar Organisasi
            $table->string('nama_organisasi', 100);
            $table->string('jabatan_dalam_organisasi', 100)->nullable();
            $table->enum('jenis_organisasi', [
                'lokal', 
                'nasional', 
                'internasional', 
                'lainnya'
            ])->default('lainnya');
            $table->string('tempat_organisasi', 200)->nullable();
            
            // Informasi Periode
            $table->date('periode_mulai');
            $table->date('periode_selesai')->nullable();
            
            // Informasi Tambahan
            $table->string('website', 200)->nullable();
            $table->text('keterangan')->nullable();
            
            // Unggah File
            $table->string('file_dokumen')->nullable();
            
            // Status pengajuan dan pelacakan
            $table->enum('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditolak'])
                    ->default('draft');
            $table->date('tgl_input')->nullable();
            $table->datetime('tgl_diajukan')->nullable();
            $table->datetime('tgl_disetujui')->nullable();
            $table->datetime('tgl_ditolak')->nullable();
            $table->text('keterangan_penolakan')->nullable();
            
            $table->timestamps();
            
            // Batasan foreign key
            $table->foreign('pegawai_id')
                    ->references('id')
                    ->on('simpeg_pegawai')
                    ->onDelete('cascade');
                    
            // Indeks untuk kinerja yang lebih baik
            // NAMA INDEKS TELAH DIUBAH UNTUK MENGHINDARI KONFLIK
            $table->index(['pegawai_id', 'status_pengajuan'], 'idx_organisasi_pegawai_status'); 
            $table->index(['jenis_organisasi'], 'idx_jenis_organisasi');
            $table->index(['periode_mulai', 'periode_selesai'], 'idx_periode');
            $table->index(['nama_organisasi'], 'idx_nama_organisasi');
            $table->index(['status_pengajuan'], 'idx_status_pengajuan');
            $table->index(['created_at'], 'idx_created_at');
            $table->index(['tgl_input'], 'idx_tgl_input');
        });
    }

    /**
     * Balikkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_organisasi');
    }
};
