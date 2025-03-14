<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisPublikasiTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_publikasi', function (Blueprint $table) {
            $table->string('kode', 3)->primary();
            $table->string('jenis_publikasi', 100);
            $table->double('bobot', 1, 2)->nullable(); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_publikasi');
    }
}
