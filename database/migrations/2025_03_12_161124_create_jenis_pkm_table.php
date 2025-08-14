<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisPKMTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_pkm', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 4);
            $table->string('nama_pkm', 100);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_pkm');
    }
}