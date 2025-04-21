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
        Schema::table('simpeg_jenis_izin', function (Blueprint $table) {
            // Foreign key untuk jenis_kehadiran_id
            $table->foreign('jenis_kehadiran_id')
                ->references('id')->on('simpeg_jenis_kehadiran')
                ->onDelete('restrict');

        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_jenis_izin', function (Blueprint $table) {
            $table->dropForeign(['jenis_kehadiran_id']);
            
        });
    }
};