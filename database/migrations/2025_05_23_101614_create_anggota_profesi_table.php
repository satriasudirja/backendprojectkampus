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
        Schema::create('anggota_profesi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_organisasi');
            $table->string('peran_kedudukan');
            $table->string('waktu_keanggotaan');
            $table->timestamp('tanggal_sinkron')->nullable();
            $table->enum('status_pengajuan', ['pending', 'approved', 'rejected', 'draft'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_profesi');
    }
};