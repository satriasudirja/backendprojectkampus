<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bahasa')) {
            Schema::create('bahasa', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('kode', 5);
                $table->string('nama_bahasa', 20);
                $table->timestamps();
            
            });
        }

        // Buat tabel simpeg_data_kemampuan_bahasa
        Schema::create('simpeg_data_kemampuan_bahasa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pegawai_id');
            $table->year('tahun');
            $table->uuid('bahasa_id');
            $table->string('nama_lembaga', 100)->nullable();
            $table->integer('kemampuan_mendengar')->nullable();
            $table->integer('kemampuan_bicara')->nullable();
            $table->integer('kemampuan_menulis')->nullable();
            $table->string('file_pendukung', 255)->nullable();
            $table->enum('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditolak'])->default('draft');
            $table->date('tgl_input')->nullable();
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('pegawai_id')->references('id')->on('simpeg_pegawai')->onDelete('cascade');
            $table->foreign('bahasa_id')->references('id')->on('bahasa')->onDelete('cascade');

            // Indexes for better performance
            $table->index(['pegawai_id', 'tahun']);
            $table->index(['pegawai_id', 'status_pengajuan']);
            $table->index('bahasa_id');
            
            // Unique constraint untuk kombinasi pegawai_id, tahun, dan bahasa_id
            $table->unique(['pegawai_id', 'tahun', 'bahasa_id'], 'unique_pegawai_tahun_bahasa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_kemampuan_bahasa');
        // Tidak drop tabel bahasa karena mungkin digunakan oleh tabel lain
    }
};