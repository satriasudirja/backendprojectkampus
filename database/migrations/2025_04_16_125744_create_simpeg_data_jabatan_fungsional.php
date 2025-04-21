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
        Schema::create('simpeg_data_jabatan_fungsional', function (Blueprint $table) {
            $table->integer('id')->primary();
            
            // Relasi ke tabel referensi
            $table->integer('jabatan_fungsional_id');
            $table->integer('pegawai_id');
            
            // Data penetapan jabatan
            $table->date('tmt_jabatan');
            $table->string('pejabat_penetap', 100);
            $table->string('no_sk', 50);
            $table->date('tanggal_sk');
            
            // Dokumen pendukung
            $table->string('file_sk_jabatan', 255)->nullable();
            
            // Metadata
            $table->date('tgl_input')->nullable();
            $table->string('status_pengajuan', 20)->default('draft'); // draft, diajukan, disetujui, ditolak
            
            $table->timestamps();

            // // Foreign keys
            // $table->foreign('jabatan_fungsional_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jabatan_fungsional')
            //       ->onDelete('restrict');

            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');

            // Indexes
            $table->index('pegawai_id');
            $table->index('jabatan_fungsional_id');
            $table->index('no_sk');
            $table->index('tmt_jabatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_jabatan_fungsional');
    }
};
