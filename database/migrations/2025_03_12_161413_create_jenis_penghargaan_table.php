<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisPenghargaanTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_penghargaan', function (Blueprint $table) {
            $table->int('id')->primary();
            $table->string('kode', 5);
            $table->string('nama_penghargaan', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_penghargaan');
    }
}