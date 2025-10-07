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
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            //

            $table->string('modified_by',255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            //
            $table->string('modified_by', 30)->nullable()->change();
        });
    }
};
