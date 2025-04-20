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
        Schema::create('simpeg_gaji_periode', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_periode', 50);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->string('status', 20)->default('draft'); // draft, proses, selesai, dll
            $table->timestamps();

            // Index untuk pencarian
            $table->index(['tgl_mulai', 'tgl_selesai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_periode');
    }
};