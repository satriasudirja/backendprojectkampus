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
        Schema::table('simpeg_jabatan_struktural', function (Blueprint $table) {
            //
            $table ->dropColumn('pangkat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_jabatan_struktural', function (Blueprint $table) {
            //
            $table->uuid('pangkat_id')->nullable()->after('jenis_jabatan_struktural_id');
        });
    }
};
