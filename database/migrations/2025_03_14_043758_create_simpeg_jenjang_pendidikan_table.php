<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_jenjang_pendidikan', function (Blueprint $table) {
             $table->bigIncrements('id');
            $table->string('jenjang_singkatan', 5);  
            $table->string('jenjang_pendidikan', 30); 
            $table->string('nama_jenjang_pendidikan_eng', 20); 
            $table->integer('urutan_jenjang_pendidikan'); 
            $table->boolean('perguruan_tinggi'); 
            $table->boolean('pasca_sarjana'); 
            $table->timestamps(); 
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_jenjang_pendidikan');
    }
};
