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
            $table->bigIncrements('id');
            
            // Data SK
            $table->string('no_sk', 50)->nullable();
            $table->date('tgl_sk')->nullable();
            $table->date('tgl_awal')->nullable();
            $table->date('tgl_akhir')->nullable();
            $table->string('pejabat_penetap', 100)->nullable();
            
            // Dokumen
            $table->string('file_hubungan_kerja', 255)->nullable();
            
            // Metadata
            // $table->date('tgl_input')->nullable();
  
            $table->string('Status')->nullable();
            $table->date('tgl_input')->nullable();
             $table->date('tgl_diajukan')->nullable();
             $table->date('tgl_disetujui')->nullable();
                $table->string('keterangan')->nullable();
                $table->string('dibuat_oleh')->nullable();
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