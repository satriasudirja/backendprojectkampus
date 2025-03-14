<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('bahasa', function (Blueprint $table) {
            $table->integer('kode', 5)->primary();
            $table->string('nama_bahasa', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahasa');
    }
};
