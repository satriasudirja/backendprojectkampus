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
        Schema::table('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
            // Add the is_aktif column if it doesn't exist
            if (!Schema::hasColumn('simpeg_master_prodi_perguruan_tinggi', 'is_aktif')) {
                $table->boolean('is_aktif')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
            // Drop the column if it exists
            if (Schema::hasColumn('simpeg_master_prodi_perguruan_tinggi', 'is_aktif')) {
                $table->dropColumn('is_aktif');
            }
        });
    }
};