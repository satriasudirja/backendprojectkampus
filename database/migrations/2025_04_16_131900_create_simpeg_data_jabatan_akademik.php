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
        Schema::create('simpeg_data_jabatan_akademik', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Foreign keys
            $table->uuid('pegawai_id');
            $table->uuid('jabatan_akademik_id');
            
            // Data jabatan
            $table->date('tmt_jabatan'); // TMT = Terhitung Mulai Tanggal
            $table->string('no_sk', 90);
            $table->date('tgl_sk'); // Diperbaiki penulisan dari 'tgl_Sk'
            $table->string('pejabat_penetap', 100);
            
            // Dokumen pendukung
            $table->string('file_jabatan', 255)->nullable();
            
            // Metadata
            $table->date('tgl_input')->nullable();
            $table->string('status_pengajuan', 20)->default('draft'); // draft, diajukan, disetujui, ditolak
            
            $table->timestamps();

            // Foreign key constraints
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');

            // $table->foreign('jabatan_akademik_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jabatan_akademik')
            //       ->onDelete('restrict');

            // Indexes
            $table->index('pegawai_id');
            $table->index('jabatan_akademik_id');
            $table->index('no_sk');
            $table->index('tmt_jabatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_jabatan_akademik');
    }
};