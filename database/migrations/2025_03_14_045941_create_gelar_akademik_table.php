<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::create('gelar_akademik', function (Blueprint $table) {
            $table->string('gelar', 5)->primary(); 
            $table->string('nama_gelar', 30); 
            $table->timestamps(); 
        });
    }


    public function down()
    {
        Schema::dropIfExists('gelar_akademik');
    }
};
