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
            $table->uuid('hubungan_kerja_id')->nullable();
            $table->foreign('hubungan_kerja_id')
                  ->references('id')->on('simpeg_hubungan_kerja')
                  ->onDelete('set null');
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {

            $table->dropForeign(['hubungan_kerja_id']);
            //
        });
    }
};
