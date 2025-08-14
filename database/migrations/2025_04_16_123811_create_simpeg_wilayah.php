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
        Schema::create('wilayah', function (Blueprint $table) {
            // Kolom utama
             $table->uuid('id')->primary(); // Menggunakan bigIncrements sebagai ganti int(100)
            
            // Data negara
            $table->string('kode_negara', 10)->nullable();
            $table->string('nama_negara', 50)->nullable();
            $table->string('kode_etnis', 10)->nullable();
            
            // Data provinsi
            $table->string('kode_provinsi', 10)->nullable();
            $table->string('nama_provinsi', 50)->nullable();
            
            // Data kabupaten/kota
            $table->string('kode_kab_kota', 10)->nullable();
            $table->string('nama_kab_kota', 50)->nullable();
            
            // Data kecamatan
            $table->string('kode_kecamatan', 10)->nullable();
            $table->string('nama_kecamatan', 50)->nullable();
            
            // Klasifikasi
            $table->string('jenis_wilayah', 50)->nullable();
            
            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('kode_negara');
            $table->index('kode_provinsi');
            $table->index('kode_kab_kota');
            $table->index('kode_kecamatan');
            $table->index('jenis_wilayah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
