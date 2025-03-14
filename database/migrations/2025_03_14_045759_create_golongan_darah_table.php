<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::create('golongan_darah', function (Blueprint $table) {
            $table->id(); 
            $table->string('golongan_darah', 2); 
            $table->timestamps(); 
        });
    }


    public function down()
    {
        Schema::dropIfExists('golongan_darah');
    }
};
