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
        Schema::create('simpeg_data_diklat', function (Blueprint $table) {
            // Kolom utama
            $table->integer('id')->primary();
            $table->integer('pegawai_id');
            // Informasi diklat
            $table->string('jenis_diklat', 100);
            $table->string('kategori_diklat', 150);
            $table->string('tingkat_diklat', 50);
            $table->string('nama_diklat', 255);
            $table->string('penyelenggara', 100);
            $table->string('peran', 30)->nullable(); // Peserta, Instruktur, dll
            $table->integer('jumlah_jam');
            
            // Sertifikat
            $table->string('no_sertifikat', 100)->nullable();
            $table->date('tgl_sertifikat')->nullable();
            $table->char('tahun_penyelenggaraan', 4);
            
            // Waktu dan tempat
            $table->string('tempat', 50);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            
            // Dokumen
            $table->string('sk_penugasan', 100)->nullable();
            
            // Metadata
            $table->date('tgl_input')->nullable();
            
            $table->timestamps();

            // // Foreign key
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');

            // Indexes
            $table->index('pegawai_id');
            $table->index('tahun_penyelenggaraan');
            $table->index('jenis_diklat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_diklat');
    }
};