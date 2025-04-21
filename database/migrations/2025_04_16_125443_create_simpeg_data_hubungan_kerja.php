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
            
            // Foreign keys
            // $table->int('hubungan_kerja_id');
            // $table->int('status_aktif_id');
            // $table->int('pegawai_id');
            
            $table->timestamps();

            // Relasi ke tabel referensi
            // $table->foreign('hubungan_kerja_id')
            //       ->references('id')
            //       ->on('simpeg_ref_hubungan_kerja')
            //       ->onDelete('restrict');

            // $table->foreign('status_aktif_id')
            //       ->references('id')
            //       ->on('simpeg_ref_status')
            //       ->onDelete('restrict');

            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');

            // Indexes
            // $table->index('no_sk');
            // $table->index('pegawai_id');
            // $table->index('tgl_awal');
            // $table->index('tgl_akhir');
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