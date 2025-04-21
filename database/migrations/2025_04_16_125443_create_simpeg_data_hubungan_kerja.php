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
        Schema::create('simpeg_data_hubungan_kerja', function (Blueprint $table) {
            $table->integer('id')->primary();
            
            // Data SK
            $table->string('no_sk', 50);
            $table->date('tgl_sk');
            $table->date('tgl_awal');
            $table->date('tgl_akhir')->nullable();
            $table->string('pejabat_penetap', 100);
            
            // Dokumen
            $table->string('file_hubungan_kerja', 255)->nullable();
            
            // Metadata
            $table->date('tgl_input')->nullable();
            
            $table->integer('hubungan_kerja_id');
            $table->integer('status_aktif_id');
            $table->integer('pegawai_id');
            
            $table->timestamps();

          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_hubungan_kerja');
    }
};