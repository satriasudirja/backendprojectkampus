<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_pekerjaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 10)->unique()->comment('Kode singkat untuk pekerjaan');
            $table->string('nama_pekerjaan', 100);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_pekerjaan');
    }
};