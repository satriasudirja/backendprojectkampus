<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::create('pekerjaan', function (Blueprint $table) {
            $table->integer('kode', 3)->primary();
            $table->string('nama_pekerjaan', 50);
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('pekerjaan');
    }
};
