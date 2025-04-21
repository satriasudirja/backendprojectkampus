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
        Schema::create('simpeg_hubungan_kerja', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 2);
            $table->string('nama_hub_kerja', 30);
            $table->boolean('status_aktif');
            $table->boolean('pns');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_hubungan_kerja');
        
    }
};
