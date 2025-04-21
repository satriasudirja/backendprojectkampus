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
        Schema::create('simpeg_data_penelitian', function (Blueprint $table) {
            $table->integer('id')->primary();
            
            // Foreign key
            $table->integer('pegawai_id');
            
            // Data pengajuan
            $table->string('jenis_kegiatan', 50);
            $table->string('status_pengajuan', 50)->default('diajukan');
            $table->date('tanggal_pengajuan');
            $table->string('sk_penugasan', 50)->nullable();
            
            // Data penelitian
            $table->string('judul_penelitian', 255)->nullable();
            $table->string('kelompok_bidang', 50)->nullable();
            $table->string('jenis_penelitian', 100)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->string('perguruan_tinggi_afiliasi', 255)->nullable();
            
            // Data publikasi
            $table->string('judul_publikasi', 255)->nullable();
            $table->string('jenis_publikasi', 50)->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->string('doi', 50)->nullable();
            $table->string('isbn', 100)->nullable();
            $table->string('issn', 100)->nullable();
            $table->string('e_issn', 100)->nullable();
            $table->string('penerbit', 100)->nullable();
            $table->string('edisi', 50)->nullable();
            $table->integer('volume')->nullable();
            $table->integer('jumlah_halaman')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_penelitian');
    }
};