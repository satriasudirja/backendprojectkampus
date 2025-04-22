<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
    {
        Schema::create('simpeg_suku', function (Blueprint $table) {
            $table->id(); 
            $table->text('nama_suku'); 
            $table->timestamps(); 
        });
    }


    public function down()
    {
        Schema::dropIfExists('simpeg_suku');
    }
};
