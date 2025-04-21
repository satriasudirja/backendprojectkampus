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
        Schema::table('simpeg_data_jabatan_akademik', function (Blueprint $table) {
            //
            $table->foreign('pegawai_id')->references('id')->on('simpeg_pegawai')->onDelete('cascade');
            $table->foreign('jabatan_akademik_id')->references('id')->on('simpeg_jabatan_akademik')->onDelete('restrict');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_jabatan_akademik', function (Blueprint $table) {
            //
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jabatan_akademik_id']);
        });
    }
};
