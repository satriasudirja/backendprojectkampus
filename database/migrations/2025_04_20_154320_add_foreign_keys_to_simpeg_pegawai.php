<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('unit_kerja_id')
                  ->references('id')
                  ->on('simpeg_unit_kerja')
                  ->onDelete('set null');

            $table->foreign('kode_status_pernikahan')
                  ->references('id')
                  ->on('simpeg_status_pernikahan')
                  ->onDelete('set null');

            $table->foreign('status_aktif_id')
                  ->references('id')
                  ->on('simpeg_status_aktif')
                  ->onDelete('restrict');

            $table->foreign('jabatan_akademik_id')
                  ->references('id')
                  ->on('simpeg_jabatan_akademik')
                  ->onDelete('set null');

            $table->foreign('suku_id')
                  ->references('id')
                  ->on('simpeg_suku')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['unit_kerja_id']);
            $table->dropForeign(['kode_status_pernikahan']);
            $table->dropForeign(['status_aktif_id']);
            $table->dropForeign(['jabatan_akademik_id']);
            $table->dropForeign(['suku_id']);
        });
    }
};