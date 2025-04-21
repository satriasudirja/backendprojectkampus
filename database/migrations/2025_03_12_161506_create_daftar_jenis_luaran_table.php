<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDaftarJenisLuaranTable extends Migration
{
    public function up()
    {
        Schema::create('daftar_jenis_luaran', function (Blueprint $table) {
            $table->int('id', 2)->primary();
            $table->string('kode', 5);
            $table->string('jenis_luaran', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('daftar_jenis_luaran');
    }
}