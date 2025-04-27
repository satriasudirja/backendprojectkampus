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
        Schema::table('simpeg_data_tes', function (Blueprint $table) {
            // Foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('cascade');

            // Foreign key untuk jenis_tes_id
            $table->foreign('jenis_tes_id')
                ->references('id')->on('simpeg_daftar_jenis_test')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_tes', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_tes_id']);
        });
    }
};