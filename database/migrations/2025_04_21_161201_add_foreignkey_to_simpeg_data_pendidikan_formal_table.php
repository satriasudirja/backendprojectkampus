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
        Schema::table('simpeg_data_pendidikan_formal', function (Blueprint $table) {
            //
            $table->index('pegawai_id');
            $table->index('jenjang_pendidikan_id');
            $table->index('tahun_lulus');

            // Foreign Keys
            $table->foreign('pegawai_id')->references('id')->on('simpeg_pegawai')->onDelete('cascade');
            $table->foreign('jenjang_pendidikan_id')->references('id')->on('simpeg_jenjang_pendidikan')->onDelete('restrict');
            $table->foreign('perguruan_tinggi_id')->references('id')->on('simpeg_master_perguruan_tinggi')->onDelete('restrict');
            $table->foreign('prodi_perguruan_tinggi_id')->references('id')->on('simpeg_master_prodi_perguruan_tinggi')->onDelete('restrict');
            $table->foreign('gelar_akademik_id')->references('id')->on('simpeg_master_gelar_akademik')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_pendidikan_formal', function (Blueprint $table) {
            //
        });
    }
};
