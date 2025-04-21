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
        Schema::table('simpeg_jabatan_struktural', function (Blueprint $table) {
            // Foreign key untuk unit_kerja_id
            $table->foreign('unit_kerja_id')
                ->references('id')->on('simpeg_unit_kerja')
                ->onDelete('restrict');

            // Foreign key untuk jenis_jabatan_struktural_id
            $table->foreign('jenis_jabatan_struktural_id')
                ->references('id')->on('simpeg_jenis_jabatan_struktural')
                ->onDelete('restrict');

            // Foreign key untuk pangkat_id
            $table->foreign('pangkat_id')
                ->references('id')->on('simpeg_master_pangkat')
                ->onDelete('restrict');

            // Foreign key untuk eselon_id
            $table->foreign('eselon_id')
                ->references('id')->on('simpeg_eselon')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_jabatan_struktural', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
            $table->dropForeign(['jenis_jabatan_struktural_id']);
            $table->dropForeign(['pangkat_id']);
            $table->dropForeign(['eselon_id']);
        });
    }
};