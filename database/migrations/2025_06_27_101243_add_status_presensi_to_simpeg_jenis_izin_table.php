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
            // Tambahkan kolom baru. Tipe 'string' bisa disesuaikan.
            // ->after('jenis_izin') bersifat opsional, hanya untuk merapikan urutan kolom.
            // $table->string('status_presensi')->nullable()->after('jenis_izin');
            //
            // $table->dropColumn('status_presensi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_jenis_izin', function (Blueprint $table) {
            // $table->dropColumn('status_presensi');
            // $table->string('status_presensi')->nullable();
        });
    }
};