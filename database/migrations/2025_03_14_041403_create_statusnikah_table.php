<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('statusnikah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 1);
            $table->string('status', 20);
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('statusnikah');
    }
};
