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
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            // Tambahkan foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                  ->references('id')->on('simpeg_pegawai')
                  ->onDelete('cascade');

            // Tambahkan foreign key untuk setting_kehadiran_id
            $table->foreign('setting_kehadiran_id')
                  ->references('id')->on('simpeg_setting_kehadiran')
                  ->onDelete('restrict');

            // Tambahkan foreign key untuk jenis_kehadiran_id
            $table->foreign('jenis_kehadiran_id')
                  ->references('id')->on('simpeg_jenis_kehadiran')
                  ->onDelete('restrict');
            $table->foreign('cuti_record_id')
                  ->references('id')->on('simpeg_cuti_record')
                  ->onDelete('cascade');
            $table->foreign('izin_record_id')
                  ->references('id')->on('simpeg_izin_record')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['setting_kehadiran_id']);
            $table->dropForeign(['jenis_kehadiran_id']);
             $table->dropForeign(['cuti_record_id']);
              $table->dropForeign(['izin_record_id']);
        });
    }
};
