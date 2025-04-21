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
        Schema::create('simpeg_gaji_komponen', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('kode_komponen', 20)->unique();
            $table->string('nama_komponen', 100);
            $table->string('jenis', 20); // contoh: tunjangan, potongan, benefit
            $table->string('rumus', 255)->nullable();
            $table->timestamps();

            // Index untuk pencarian
            $table->index(['kode_komponen', 'jenis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_komponen');
    }
};