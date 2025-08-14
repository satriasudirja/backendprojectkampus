<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            // Pastikan kolom tujuan ada di tabel lain

            $table->foreign('role_id')
                  ->references('id')->on('simpeg_users_roles')
                  ->onDelete('set null');

            $table->foreign('unit_kerja_id')
                  ->references('id')->on('simpeg_unit_kerja')
                  ->onDelete('set null');

            $table->foreign('kode_status_pernikahan')
                  ->references('id')->on('simpeg_status_pernikahan')
                  ->onDelete('set null');

            $table->foreign('status_aktif_id')
                  ->references('id')->on('simpeg_status_aktif')
                  ->onDelete('set null');

            $table->foreign('jabatan_fungsional_id')
                  ->references('id')->on('simpeg_fungsional_akademik')
                  ->onDelete('set null');

            $table->foreign('suku_id')
                  ->references('id')->on('simpeg_suku')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['unit_kerja_id']);
            $table->dropForeign(['kode_status_pernikahan']);
            $table->dropForeign(['status_aktif_id']);
            $table->dropForeign(['jabatan_fungsional_id']);
            $table->dropForeign(['suku_id']);
        });
    }
};
