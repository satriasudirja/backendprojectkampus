<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {




             Schema::create('simpeg_jenis_publikasi', function (Blueprint $table) {
               $table->bigIncrements('id');
            $table->string('kode', 5)->nullable();
            $table->string('jenis_publikasi', 50);
      
            $table->timestamps();
        });






        Schema::table('simpeg_data_publikasi', function (Blueprint $table) {
            // Foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('cascade');

            // Foreign key untuk jenis_publikasi_id
            $table->foreign('jenis_publikasi_id')
                ->references('id')->on('simpeg_jenis_publikasi')
                ->onDelete('restrict');

            // Foreign key untuk jenis_luaran_id
            $table->foreign('jenis_luaran_id')
                ->references('id')->on('simpeg_daftar_jenis_luaran')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_publikasi', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_publikasi_id']);
            $table->dropForeign(['jenis_luaran_id']);
        });
    }
};