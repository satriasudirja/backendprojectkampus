<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSimpegJenisPenghargaanTable extends Migration
{
    public function up()
{
    Schema::create('simpeg_jenis_penghargaan', function (Blueprint $table) {
        $table->id();
        $table->string('kode')->unique();
        $table->string('nama');
        $table->timestamps();
    });
}

    public function down()
    {
        Schema::dropIfExists('Simpeg_jenis_penghargaan');
    }
}