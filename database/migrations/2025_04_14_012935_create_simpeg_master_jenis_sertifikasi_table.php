<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
        {
            Schema::create('simpeg_master_jenis_sertifikasi', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('kode', 5);
                $table->string('nama_sertifikasi', 50);
                $table->string('jenis_sertifikasi', 50);
                $table->timestamps();
            });
            
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_master_jenis_sertifikasi');
    }
};
