<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMediaPublikasiTable extends Migration
{
    public function up()
    {
        Schema::create('media_publikasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('media_publikasi');
    }
}