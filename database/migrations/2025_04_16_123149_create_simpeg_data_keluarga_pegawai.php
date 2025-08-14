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
        Schema::create('simpeg_data_keluarga_pegawai', function (Blueprint $table) {
            // Kolom utama
            $table->uuid('id')->primary();
            $table->uuid('pegawai_id');
            
            // Data umum keluarga
            $table->string('nama', 100)->nullable();
            $table->string('jenis_kelamin', 50)->nullable();
            $table->string('status_orangtua', 50)->nullable();
            $table->string('tempat_lahir', 50)->nullable();
            $table->date('tgl_lahir')->nullable();
            $table->integer('umur')->nullable();
            $table->integer('anak_ke')->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon', 55)->nullable();
            $table->date('tgl_input')->nullable();
            $table->string('pekerjaan', 100)->nullable();
            
            // Data dokumen
            $table->string('kartu_nikah', 100)->nullable();
            $table->string('file_akte', 100)->nullable();
            
            // Data khusus anak
            $table->string('pekerjaan_anak', 50)->nullable();
            
            $table->string('keterangan')->nullable();
            
            // Data pasangan
            $table->string('nama_pasangan', 100)->nullable();
            $table->boolean('pasangan_berkerja_dalam_satu_instansi')->default(false)->nullable();
            
           
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('pegawai_id');
            $table->index('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_keluarga_pegawai');
    }
};