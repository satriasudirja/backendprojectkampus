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
        Schema::table('simpeg_jabatan_fungsional', function (Blueprint $table) {
            // Foreign key untuk jabatan_akademik_id
            $table->foreign('jabatan_akademik_id')
                ->references('id')->on('simpeg_jabatan_akademik')
                ->onDelete('restrict');

            // Foreign key untuk pangkat_id
            $table->foreign('pangkat_id')
                ->references('id')->on('simpeg_master_pangkat')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_jabatan_fungsional', function (Blueprint $table) {
            $table->dropForeign(['jabatan_akademik_id']);
            $table->dropForeign(['pangkat_id']);
        });
    }
};