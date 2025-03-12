<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisPenghargaanTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_penghargaan', function (Blueprint $table) {
            $table->string('kode', 4)->primary();
            $table->string('penghargaan', 30);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_penghargaan');
    }
}