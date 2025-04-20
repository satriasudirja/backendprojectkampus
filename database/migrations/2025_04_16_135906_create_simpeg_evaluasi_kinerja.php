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
        Schema::create('simpeg_evaluasi_kerja', function (Blueprint $table) {
            // Kolom utama
            $table->uuid('id')->primary();
            
            // Identifikasi evaluasi
            $table->uuid('pegawai_id'); // Diperbaiki dari 'pegmed_id'
            $table->uuid('penilai_id');
            $table->uuid('atasan_penilai_id'); // Diperbaiki dari 'atsan_penilai_id'
            $table->char('periode_tahun', 10); // Diperbaiki dari 'periods_tahun'
            $table->date('tanggal_penilaian'); // Diperbaiki dari 'tanggal_penilatan'
            
            // Komponen penilaian
            $table->float('nilai_kehadiran', 8, 2);
            $table->float('nilai_pendidikan', 8, 2);
            $table->float('nilai_penelitian', 8, 2); // Diperbaiki dari 'nilai_pemelitian'
            $table->float('nilai_pengabdian', 8, 2);
            $table->float('nilai_penunjang1', 8, 2); // Diperbaiki dari 'nilai_pemunjang1'
            $table->float('nilai_penunjang2', 8, 2);
            $table->float('nilai_penunjang3', 8, 2);
            $table->float('nilai_penunjang4', 8, 2);
            
            // Hasil evaluasi
            $table->float('total_nilai', 10, 2);
            $table->string('sebutan_total', 20); // Diperbaiki dari 'sehutan_total'
            
            // Metadata
            $table->date('tgl_input')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('cascade');
                  
            $table->foreign('penilai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('restrict');
                  
            $table->foreign('atasan_penilai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('restrict');

            // Indexes
            $table->index('pegawai_id');
            $table->index('periode_tahun');
            $table->index('total_nilai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_evaluasi_kerja');
    }
};
