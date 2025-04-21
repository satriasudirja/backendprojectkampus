<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('universitas_luar', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('kode', 10);
            $table->string('nama_universitas', 50); 
            $table->text('alamat'); 
            $table->string('no_telp', 20); 
            $table->timestamps(); 
        });
    }

    public function down()
    {
        Schema::dropIfExists('universitas_luar');
    }
};
