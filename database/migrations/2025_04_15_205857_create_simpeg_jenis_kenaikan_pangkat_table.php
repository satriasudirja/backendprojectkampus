<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simpeg_jenis_kenaikan_pangkat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 2);
            $table->string('jenis_pangkat', 20);
            $table->timestamps();
        });
        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jenis_kenaikan_pangkat');
    }
};
