<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGelarAkademikTable extends Migration
{
    public function up()
    {
        Schema::create('gelar_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('gelar', 7);
            $table->string('nama_gelar', 20);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gelar_akademik');
    }
}