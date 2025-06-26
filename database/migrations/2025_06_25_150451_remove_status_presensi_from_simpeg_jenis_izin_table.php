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
        Schema::table('simpeg_jenis_izin', function (Blueprint $table) {
            //
            $table->dropColumn('status_presensi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_jenis_izin', function (Blueprint $table) {
            //
            $table->string('status_presensi', 20);
        });
    }
};
