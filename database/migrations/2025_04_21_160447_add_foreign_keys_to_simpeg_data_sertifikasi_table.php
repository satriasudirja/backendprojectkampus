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
        Schema::table('simpeg_data_sertifikasi', function (Blueprint $table) {
            // Foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('cascade');

            // Foreign key untuk jenis_sertifikasi_id
            $table->foreign('jenis_sertifikasi_id')
                ->references('id')->on('simpeg_master_jenis_sertifikasi')
                ->onDelete('restrict');

            // Foreign key untuk bidang_ilmu_id
            $table->foreign('bidang_ilmu_id')
                ->references('id')->on('simpeg_rumpun_bidang_ilmu')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_sertifikasi', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_sertifikasi_id']);
            $table->dropForeign(['bidang_ilmu_id']);
        });
    }
};