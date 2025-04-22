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
        Schema::table('simpeg_gaji_detail', function (Blueprint $table) {
            // Foreign key untuk gaji_slip_id
            $table->foreign('gaji_slip_id')
                ->references('id')->on('simpeg_gaji_slip')
                ->onDelete('cascade');

            // Foreign key untuk komponen_id
            $table->foreign('komponen_id')
                ->references('id')->on('simpeg_gaji_komponen')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_gaji_detail', function (Blueprint $table) {
            $table->dropForeign(['gaji_slip_id']);
            $table->dropForeign(['komponen_id']);
        });
    }
};