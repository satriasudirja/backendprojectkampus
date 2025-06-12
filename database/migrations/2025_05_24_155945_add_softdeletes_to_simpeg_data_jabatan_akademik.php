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
        Schema::table('simpeg_data_jabatan_akademik', function (Blueprint $table) {
            // Check if deleted_at column doesn't exist before adding it
            if (!Schema::hasColumn('simpeg_data_jabatan_akademik', 'deleted_at')) {
                $table->softDeletes(); // Note: use lowercase 'softDeletes'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_jabatan_akademik', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};