<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('settinghari', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID primary key
            $table->integer('kode'); // Remove the second parameter - just a regular integer column
            $table->string('nama_hari', 10); // Nama Hari (10)
            $table->string('jenis_hari', 15); // Jenis Hari (15)
            $table->timestamps();
            
            // Optional: Add unique constraint on kode if needed
            // $table->unique('kode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('settinghari');
    }
};