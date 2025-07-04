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
        Schema::create('simpeg_master_pangkat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pangkat', 6);
            $table->string('nama_golongan', 30);
            $table->decimal('tunjangan', 15, 2)->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_master_pangkat');
    }
};