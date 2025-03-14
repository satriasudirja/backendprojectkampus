<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
    {
        Schema::create('bank', function (Blueprint $table) {
            $table->string('kode', 3)->primary(); 
            $table->string('bank', 30); 
            $table->timestamps(); 
        });
    }


    public function down()
    {
        Schema::dropIfExists('bank');
    }
};
