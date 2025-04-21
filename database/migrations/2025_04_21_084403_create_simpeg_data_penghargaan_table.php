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
            $table->id();
            
            // Foreign key
            $table->integer('pegawai_id');
            
            // Data penghargaan
            $table->string('kategori_penghargaan', 100);
            $table->string('tingkat_penghargaan', 50);
            $table->string('jenis_penghargaan', 100);
            $table->string('nama_penghargaan', 255);
            $table->date('tanggal');  // Diperbaiki dari 'tanggai' ke 'tanggal'
            $table->string('instansi_pemberi', 255);
            $table->string('status_pengajuan', 50)->default('diajukan');
            
            $table->timestamps();
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