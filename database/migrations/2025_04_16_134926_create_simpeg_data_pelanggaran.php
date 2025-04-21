<<?php

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
        Schema::create('simpeg_data_pelanggaran', function (Blueprint $table) {
            // Tambahkan relasi ke tabel pegawai
            $table->integer('id')->primary();
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade'); // atau 'restrict' sesuai kebutuhan

            // // Tambahkan relasi ke tabel jenis_pelanggaran
            // $table->foreign('jenis_pelanggaran_id')
            //       ->references('id')
            //       ->on('simpeg_jenis_pelanggaran')
            //       ->onDelete('restrict');

            $table->date('tgl_pelanggaran');
            $table->string('no_sk', 100);
            $table->date('tgl_sk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('simpeg_data_pelanggaran', function (Blueprint $table) {
           
        // });
    }
};