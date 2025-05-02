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
        // Tambahkan kolom akreditasi jika belum ada
        if (!Schema::hasColumn('simpeg_master_prodi_perguruan_tinggi', 'akreditasi')) {
            Schema::table('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
                $table->string('akreditasi', 5)->nullable()->after('no_telp');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus kolom akreditasi jika ada
        if (Schema::hasColumn('simpeg_master_prodi_perguruan_tinggi', 'akreditasi')) {
            Schema::table('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
                $table->dropColumn('akreditasi');
            });
        }
    }
};