<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisSkTable extends Migration
{
    public function up()
    {
        Schema::create('simpeg_daftar_jenis_sk', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5);
            $table->string('jenis_sk', 20);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_sk');
    }
}