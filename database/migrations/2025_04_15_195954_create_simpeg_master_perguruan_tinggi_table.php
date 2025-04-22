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
        Schema::create('simpeg_master_perguruan_tinggi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode', 10);
            $table->string('nama_universitas', 100);
            $table->text('alamat');
            $table->string('no_telp', 30);
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_master_perguruan_tinggi');
    }
};
