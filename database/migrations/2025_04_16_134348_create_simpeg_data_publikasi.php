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
        Schema::create('simpeg_data_publikasi', function (Blueprint $table) {
            // Kolom utama
            $table->integer('id')->primary();
            $table->integer('pegawai_id');
            
            // Referensi
            $table->integer('jenis_publikasi_id');
            $table->integer('jenis_luaran_id'); // Diperbaiki dari 'jenis_luanan_id'
            
            // Data publikasi
            $table->text('judul');
            $table->text('judul_asli')->nullable();
            $table->string('nama_jurnal', 100)->nullable();
            $table->date('tgl_terbit');
            $table->string('penerbit', 100)->nullable();
            
            // Detail publikasi
            $table->string('edisi', 15)->nullable();
            $table->integer('volume')->nullable();
            $table->integer('nomor')->nullable();
            $table->string('halaman', 20)->nullable();
            $table->integer('jumlah_halaman')->nullable();
            
            // Identifikasi
            $table->string('doi', 100)->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('issn', 10)->nullable();
            $table->string('e_issn', 10)->nullable();
            
            // Klasifikasi
            $table->boolean('seminar')->default(false);
            $table->boolean('prosiding')->default(false);
            
            // Paten
            $table->string('nomor_paten', 100)->nullable();
            $table->string('pemberi_paten', 100)->nullable();
            
            // Metadata
            $table->text('keterangan')->nullable(); // Diperbaiki dari 'keteragan'
            $table->string('no_sk_penugasan', 50)->nullable();
            $table->date('tgl_input')->nullable();
            $table->string('status_pengajuan', 20)->default('draft'); // draft, submitted, approved, rejected
            
            $table->timestamps();

            // // Foreign keys
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');
                  
            // $table->foreign('jenis_publikasi_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jenis_publikasi')
            //       ->onDelete('restrict');
                  
            // $table->foreign('jenis_layanan_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jenis_layanan')
            //       ->onDelete('restrict');

            // Indexes
            $table->index('pegawai_id');
            $table->index('tgl_terbit');
            $table->index('status_pengajuan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_publikasi');
    }
};