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
        Schema::table('simpeg_cuti_record', function (Blueprint $table) {
            // Tambahkan foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('cascade');

            // Tambahkan foreign key untuk jenis_cuti_id
            $table->foreign('jenis_cuti_id')
                  ->references('id')
                  ->on('simpeg_daftar_cuti')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_cuti_record', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_cuti_id']);
        });
    }
};
