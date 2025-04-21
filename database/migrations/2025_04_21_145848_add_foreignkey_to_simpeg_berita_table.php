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
        Schema::table('simpeg_berita', function (Blueprint $table) {
            // Tambahkan foreign key ke tabel simpeg_unit_kerja
            $table->foreign('unit_kerja_id')
                  ->references('id')
                  ->on('simpeg_unit_kerja')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_berita', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
        });
    }
};
