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
        Schema::create('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('perguruan_tinggi_id');
            $table->uuid('jenjang_pendidikan_id');
            
            $table->string('kode', 10);
            $table->string('nama_prodi', 100);
            $table->text('jenjang')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telp', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_master_prodi_perguruan_tinggi');
        Schema::create('simpeg_master_prodi_perguruan_tinggi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('perguruan_tinggi_id');
            $table->integer('jenjang_pendidikan_id');
            $table->string('kode', 10);
            $table->string('nama_prodi', 100);
            $table->text('jenjang')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telp', 30)->nullable();
            $table->timestamps();
        });
    }
};
