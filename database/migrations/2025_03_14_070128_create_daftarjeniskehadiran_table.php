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
    Schema::create('daftarjeniskehadiran', function (Blueprint $table) {
        $table->string('kode', 5)->primary(); // Kode varchar(5) primary key
        $table->string('jenis_kehadiran', 30); // Jenis Kehadiran varchar(30)
        $table->string('warna', 6); // Warna varchar(6)
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('daftarjeniskehadiran');
}
};
