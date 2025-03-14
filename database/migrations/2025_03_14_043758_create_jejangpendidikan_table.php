<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jejangpendidikan', function (Blueprint $table) {
            $table->id(); 
            $table->string('jenjang', 4); 
            $table->string('nama', 20); 
            $table->string('jenjang_pendidikan', 30); 
            $table->text('nama_jenjang_pendidikan_en'); 
            $table->boolean('urutan_jenjang_pendidikan'); 
            $table->boolean('perguruan_tinggi'); 
            $table->boolean('pasca_sarjana'); 
            $table->timestamps(); 
        });
    }

    public function down()
    {
        Schema::dropIfExists('jejangpendidikan');
    }
};
