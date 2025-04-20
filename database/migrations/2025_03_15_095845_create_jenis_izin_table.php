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
    Schema::create('jenis_izin', function (Blueprint $table) {
        $table->string('kode', 3)->primary(); // Kode varchar(3) primary key
        $table->string('jenis_izin', 50); // Jenis Izin varchar(50)
        $table->string('status_presensi', 30); // Status Presensi varchar(30)
        $table->integer('maksimal', 2); // Maksimal (hari) int(2)
        $table->boolean('potong_cuti'); // Potong Cuti boolean
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('jenis_izin');
}
};
