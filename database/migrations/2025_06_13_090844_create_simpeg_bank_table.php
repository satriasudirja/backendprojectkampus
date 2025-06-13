<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_bank', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode', 10)->unique();
            $table->string('nama_bank', 100);
            $table->timestamps();
            $table->softDeletes(); // Untuk fitur hapus sementara
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_bank');
    }
};