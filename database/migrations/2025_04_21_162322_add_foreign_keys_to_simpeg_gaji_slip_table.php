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
        Schema::table('simpeg_gaji_slip', function (Blueprint $table) {
            // Foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('cascade');

            // Foreign key untuk periode_id
            $table->foreign('periode_id')
                ->references('id')->on('simpeg_gaji_periode')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_gaji_slip', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['periode_id']);
        });
    }
};