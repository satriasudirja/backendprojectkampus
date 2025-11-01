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
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            //
            // Tambah kolom metode absensi
            $table->enum('metode_absensi', ['qr_code', 'manual', 'foto'])->default('qr_code')->after('check_sum_absensi');
            
            // Ubah foto menjadi nullable
            $table->string('foto_masuk')->nullable()->change();
            $table->string('foto_keluar')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            //
            $table->dropColumn('metode_absensi');
        });
    }
};
