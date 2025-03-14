<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisSkTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_sk', function (Blueprint $table) {
            $table->string('kode', 3)->primary();
            $table->string('jenis_sk', 20);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_sk');
    }
}