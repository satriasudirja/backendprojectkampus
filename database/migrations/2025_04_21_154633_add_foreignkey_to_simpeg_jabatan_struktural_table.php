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
        Schema::table('simpeg_jabatan_struktural', function (Blueprint $table) {
            //
              $table->foreign('unit_kerja_id')
                  ->references('id')
                  ->on('simpeg_unit_kerja')
                  ->onDelete('cascade');

            $table->foreign('jenis_jabatan_struktural_id')
                  ->references('id')
                  ->on('simpeg_jenis_jabatan_struktural')
                  ->onDelete('cascade');

            $table->foreign('pangkat_id')
                  ->references('id')
                  ->on('simpeg_master_pangkat')
                  ->onDelete('cascade');

            $table->foreign('eselon_id')
                  ->references('id')
                  ->on('simpeg_eselon')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_jabatan_struktural', function (Blueprint $table) {
            //
            $table->dropForeign(['unit_kerja_id']);
            $table->dropForeign(['jenis_jabatan_struktural_id']);
            $table->dropForeign(['pangkat_id']);
            $table->dropForeign(['eselon_id']);
        });
    }
};
