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
            $table->uuid('jabatan_struktural_id')
                  ->nullable()
                  ->after('jabatan_fungsional_id');

            $table->foreign('jabatan_struktural_id')
                  ->references('id')->on('simpeg_jenis_jabatan_struktural')
                  ->onDelete('set null');
            //
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropForeign(['jabatan_struktural_id']);
            //
        });
    }
};
