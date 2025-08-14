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
        Schema::create('simpeg_jenis_hari', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 2);
            $table->string('nama_hari', 10);
            $table->boolean('jenis_hari');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jenis_hari');
    }
};
