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
        Schema::table('simpeg_data_jabatan_fungsional', function (Blueprint $table) {
            //
                       // // Foreign keys
            $table->foreign('jabatan_fungsional_id')
                  ->references('id')
                  ->on('simpeg_jabatan_fungsional')
                  ->onDelete('restrict');

            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_jabatan_fungsional', function (Blueprint $table) {
            //
            $table->dropForeign(['jabatan_fungsional_id']);
            $table->dropForeign(['pegawai_id']);
        });
    }
};
