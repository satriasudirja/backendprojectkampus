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
        Schema::table('simpeg_data_hubungan_kerja', function (Blueprint $table) {
            // Foreign key ke tabel pegawai
            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('cascade');

            // Foreign key ke jenis hubungan kerja (misal PNS, Honorer, dll)
            $table->foreign('hubungan_kerja_id')
                  ->references('id')
                  ->on('simpeg_hubungan_kerja')
                  ->onDelete('restrict');

            // Foreign key ke status aktif (misal aktif, cuti, pensiun, dll)
            $table->foreign('status_aktif_id')
                  ->references('id')
                  ->on('simpeg_status_aktif')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_hubungan_kerja', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['hubungan_kerja_id']);
            $table->dropForeign(['status_aktif_id']);
        });
    }
};
