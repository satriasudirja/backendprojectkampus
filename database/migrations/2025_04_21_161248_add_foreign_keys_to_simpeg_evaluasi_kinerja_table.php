<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::table('simpeg_evaluasi_kinerja', function (Blueprint $table) {
            // Foreign key untuk pegawai_id (yang dinilai)
            $table->foreign('pegawai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('cascade');

            // Foreign key untuk penilai_id
            $table->foreign('penilai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('restrict');

            // Foreign key untuk atasan_penilai_id
            $table->foreign('atasan_penilai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_evaluasi_kinerja', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['penilai_id']);
            $table->dropForeign(['atasan_penilai_id']);
        });
    }
};