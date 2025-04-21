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
        Schema::create('simpeg_jam_kerja', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('jenis_jam_kerja', 50);
            $table->boolean('jam_normal');
            $table->string('jam_datang', 20);
            $table->string('jam_pulang', 20);
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jam_kerja');
    }
};
