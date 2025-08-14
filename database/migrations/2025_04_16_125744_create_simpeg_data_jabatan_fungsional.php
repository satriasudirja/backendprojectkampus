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
        // Perintah untuk MEMBUAT tabel baru
        Schema::create('simpeg_data_jabatan_fungsional', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Kolom untuk foreign key (relasi akan dibuat di file migrasi terpisah)
            $table->uuid('jabatan_fungsional_id');
            $table->uuid('pegawai_id');

            // Kolom utama sesuai model
            $table->date('tmt_jabatan');
            $table->string('pejabat_penetap', 100);
            $table->string('no_sk', 100);
            $table->date('tanggal_sk');
            $table->string('file_sk_jabatan')->nullable();
            $table->date('tgl_input')->nullable();
            
            // Kolom status dan tanggal-tanggal terkait
            $table->string('status_pengajuan', 50)->default('draft');
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            
            // Timestamps standar (created_at, updated_at) dan soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Index untuk optimasi query
            $table->index('pegawai_id');
            $table->index('jabatan_fungsional_id');
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