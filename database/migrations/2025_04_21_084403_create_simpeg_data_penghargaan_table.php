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
        Schema::create('simpeg_data_penghargaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Foreign key
            $table->unsignedBigInteger('pegawai_id');
            
            // Data penghargaan
            $table->string('jenis_penghargaan', 100)->nullable();
            $table->string('nama_penghargaan', 255)->nullable();
            $table->string('no_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tanggal_penghargaan')->nullable();
            $table->string('file_penghargaan')->nullable(); // Diperbaiki dari date menjadi string
            $table->string('keterangan', 255)->nullable();

            // --- KOLOM BARU UNTUK SISTEM PENGAJUAN ---
            $table->string('status_pengajuan', 50)->default('draft');
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            // ------------------------------------------
            
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('pegawai_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_penghargaan');
    }
};