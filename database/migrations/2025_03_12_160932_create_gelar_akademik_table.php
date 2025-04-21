<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGelarAkademikTable extends Migration
{
    public function up()
    {
        Schema::create('simpeg_master_gelar_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('gelar', 15);
            $table->string('nama_gelar', 100);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_master_gelar_akademik');
    }
}