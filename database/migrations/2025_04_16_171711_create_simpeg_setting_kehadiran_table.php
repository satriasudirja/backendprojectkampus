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
            $table->uuid('id')->primary();
            $table->string('nama_gedung', 50);
            $table->float('latitude');
            $table->float('longitude');
            $table->float('radius');
            $table->boolean('berlaku_keterlambatan');
            $table->integer('toleransi_terlambat');
            $table->boolean('berlaku_pulang_cepat');
            $table->integer('toleransi_pulang_cepat');
            $table->boolean('wajib_foto');
            $table->boolean('wajib_isi_rencana_kegiatan');
            $table->boolean('wajib_isi_realisasi_kegiatan');
            $table->boolean('wajib_presensi_dilokasi');
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
