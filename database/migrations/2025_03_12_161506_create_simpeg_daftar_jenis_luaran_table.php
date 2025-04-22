<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_daftar_jenis_luaran', function (Blueprint $table) {
             $table->bigIncrements('id');
            $table->string('kode', 5);
            $table->string('jenis_luaran', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_daftar_jenis_luaran');
    }
};