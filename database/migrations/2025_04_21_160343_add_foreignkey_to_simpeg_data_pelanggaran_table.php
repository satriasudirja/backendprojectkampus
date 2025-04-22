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
        Schema::table('simpeg_data_pelanggaran', function (Blueprint $table) {
            //
                 
                  $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('cascade');

            // Tambahkan relasi ke tabel jenis_pelanggaran
            $table->foreign('jenis_pelanggaran_id')
                  ->references('id')
                  ->on('simpeg_jenis_pelanggaran')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_pelanggaran', function (Blueprint $table) {
            //
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_pelanggaran_id']);
        });
    }
};
