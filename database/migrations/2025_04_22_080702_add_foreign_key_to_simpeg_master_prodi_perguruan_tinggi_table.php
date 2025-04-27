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
            //
            $table->foreign('perguruan_tinggi_id')->references('id')->on('simpeg_master_perguruan_tinggi')->onDelete('cascade');
            $table->foreign('jenjang_pendidikan_id')->references('id')->on('simpeg_jenjang_pendidikan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
            //
        });
    }
};
