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
        Schema::create('simpeg_data_tes', function (Blueprint $table) {
            $table->int('id')->primary();
            
            // Foreign keys
            $table->int('pegawai_id');
            $table->int('jenis_tes_id');
            
            // Data tes
            $table->string('nama_tes', 100);
            $table->string('penyelenggara', 100);
            $table->date('tgl_tes');
            $table->float('skor', 8, 2); // float4 equivalent with precision
            
            // Dokumen pendukung
            $table->string('file_pendukung', 255)->nullable();
            
            // Metadata
            $table->date('tgl_input')->nullable();
            
            $table->timestamps();

            // Foreign key constraints
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');

            // $table->foreign('jenis_tes_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jenis_tes')
            //       ->onDelete('restrict');

            // Indexes
            $table->index('pegawai_id');
            $table->index('jenis_tes_id');
            $table->index('tgl_tes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_tes');
    }
};
