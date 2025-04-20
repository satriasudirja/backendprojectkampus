<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('statusnikah', function (Blueprint $table) {
            $table->string('kode', 1)->primary();
            $table->string('status', 20);
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('statusnikah');
    }
};
