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
        Schema::create('simpeg_jenis_izin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jenis_kehadiran_id');
            $table->string('kode', 5);
            $table->string('jenis_izin', 50);
            $table->string('status_presensi');
            $table->string('izin_max', 3);
            $table->boolean('potong_cuti');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jenis_izin');
    }
};
