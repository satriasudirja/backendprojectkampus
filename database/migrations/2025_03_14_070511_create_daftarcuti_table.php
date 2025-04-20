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
    Schema::create('daftarcuti', function (Blueprint $table) {
        $table->string('kode', 3)->primary(); // Kode varchar(3) primary key
        $table->string('nama_jenis_cuti', 20); // Nama Jenis Cuti varchar(20)
        $table->integer('standar_cuti', 2); // Standar Cuti (hari) int(2)
        $table->string('format_nomor_surat', 30); // Format Nomor Surat varchar(30)
        $table->text('keterangan'); // Keterangan text
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('daftarcuti');
}
};
