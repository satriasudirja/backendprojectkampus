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
        Schema::create('simpeg_status_aktif', function (Blueprint $table) {
            $table->int('id')->primary();
            $table->string('kode', 2);
            $table->string('nama_status_aktif', 30);
            $table->boolean('status_keluar');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_status_aktif');
    }
};
