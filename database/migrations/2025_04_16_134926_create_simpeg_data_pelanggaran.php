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
            $table->uuid('id')->primary();
            $table->uuid('pegawai_id');
            $table->uuid('jenis_pelanggaran_id');
            $table->date('tgl_pelanggaran');
            $table->string('no_sk', 100);
            $table->date('tgl_sk');
            $table->string('keterangan', 255);
            $table->string('file_foto', 255);
             $table->timestamps();
            $table->softDeletes();
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