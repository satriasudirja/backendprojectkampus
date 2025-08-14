<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function up()
{
    Schema::create('simpeg_daftar_cuti', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('kode', 5); // Kode varchar(3) primary key
        $table->string('nama_jenis_cuti', 50); // Nama Jenis Cuti varchar(20)
        $table->integer('standar_cuti'); // Standar Cuti (hari) int(2)
        $table->string('format_nomor_surat', 50); // Format Nomor Surat varchar(30)
        $table->text('keterangan'); // Keterangan text
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('simpeg_daftar_cuti');
}
};
