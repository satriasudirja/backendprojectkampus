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
        Schema::create('simpeg_setting_kehadiran', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_gedung', 50);
            $table->float('latitude');
            $table->float('longitude'); 
            $table->float('radius')->nullable();
            $table->boolean('berlaku_keterlambatan')->nullable();
            $table->integer('toleransi_terlambat')->nullable();
            $table->boolean('berlaku_pulang_cepat')->nullable();
            $table->integer('toleransi_pulang_cepat')->nullable();
            $table->boolean('wajib_foto')->nullable();
            $table->boolean('wajib_isi_rencana_kegiatan')->nullable();
            $table->boolean('wajib_isi_realisasi_kegiatan')->nullable();
            $table->boolean('wajib_presensi_dilokasi')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_setting_kehadiran');
    }
};
