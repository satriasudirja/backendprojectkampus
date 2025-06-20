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
        Schema::create('simpeg_data_pangkat', function (Blueprint $table) {
            // Kolom utama
            $table->bigIncrements('id'); // Diubah dari FK ke PK karena ini tabel utama
            $table->integer('pegawai_id');
            
            // Referensi
            $table->integer('jenis_sk_id');
            $table->integer('jenis_kenaikan_pangkat_id'); // Diperbaiki dari 'jenis_kenalkan_pangkat_id'
            $table->integer('pangkat_id');
            
            // Data kepangkatan
            $table->date('tmt_pangkat');
            $table->string('no_sk', 50);
            $table->date('tgl_sk');
            $table->string('pejabat_penetap', 100);
            
            // Masa kerja
            $table->string('masa_kerja_tahun', 2);
            $table->string('masa_kerja_bulan', 2);
            $table->boolean('acuan_masa_kerja')->default(false);
            
            // Dokumen
            $table->string('file_pangkat', 255)->nullable();
            
            // Metadata
            $table->date('tgl_input')->nullable();
            $table->string('status_pengajuan', 20)->default('draft'); // draft, diajukan, disetujui, ditolak
            $table->boolean('is_aktif')->default(false);

            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            
            $table->timestamps();

            // // Foreign keys
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');
                  
            // $table->foreign('jenis_sk_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jenis_sk')
            //       ->onDelete('restrict');
                  
            // $table->foreign('jenis_kepangkatan_id')
            //       ->references('id')
            //       ->on('simpeg_ref_jenis_kepangkatan')
            //       ->onDelete('restrict');
                  
            // $table->foreign('pangkat_id')
            //       ->references('id')
            //       ->on('simpeg_ref_pangkat')
            //       ->onDelete('restrict');

            // Indexes
            $table->index('pegawai_id');
            $table->index('pangkat_id');
            $table->index('tmt_pangkat');
            $table->index('is_aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_pangkat');
    }
};