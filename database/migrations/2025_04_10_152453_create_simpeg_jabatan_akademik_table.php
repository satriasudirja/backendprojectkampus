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
        Schema::create('simpeg_jabatan_akademik', function (Blueprint $table) {
             $table->bigIncrements('id');
        
            $table->integer('role_id'); 
            $table->string('kode', 2); 
            $table->string('jabatan_akademik', 50); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jabatan_akademik');
    }
};
