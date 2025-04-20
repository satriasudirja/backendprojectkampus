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
        Schema::create('simpeg_unit_kerja', function (Blueprint $table) {
            // Kolom utama
            $table->uuid('id')->primary();
            $table->string('kode_unit', 20)->unique();
            $table->string('nama_unit', 100);
            
            // Relasi hierarkis
            $table->string('parent_unit_id', 20)->nullable();
            $table->string('jenis_unit_id', 20)->nullable();
            $table->string('tk_pendidikan_id', 20)->nullable();
            
            // Kontak dan alamat
            $table->string('alamat', 255)->nullable();
            $table->string('telepon', 15)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('alamat_email', 50)->nullable();
            
            // Data akreditasi
            $table->string('akreditasi_id', 15)->nullable();
            $table->string('no_sk_akreditasi', 50)->nullable();
            $table->date('tanggal_akreditasi')->nullable(); // Diperbaiki dari 'tanggal_akreditas'
            
            // Data pendirian
            $table->string('no_sk_pendirian', 50)->nullable();
            $table->date('tanggal_sk_pendirian')->nullable();
            
            // Fasilitas
            $table->string('gedung', 50)->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index('kode_unit');
            $table->index('parent_unit_id');
            $table->index('jenis_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_unit_kerja');
    }
};