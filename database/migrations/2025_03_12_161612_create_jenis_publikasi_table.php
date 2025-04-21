<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisPublikasiTable extends Migration
{
    public function up()
    {
        Schema::create('simpeg_jenis_publikasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5);
            $table->string('jenis_publikasi', 50);
            // $table->double('bobot', 1, 2)->nullable(); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_publikasi');
    }
}
