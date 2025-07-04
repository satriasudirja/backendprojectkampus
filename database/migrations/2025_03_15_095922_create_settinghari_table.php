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
        $table->integer('kode', 1)->primary(); // Kode int(1) primary key
        $table->string('nama_hari', 10); // Nama Hari (10)
        $table->string('jenis_hari', 15); // Jenis Hari (15)
        $table->timestamps();
        
    });
}

public function down()
{
    Schema::dropIfExists('settinghari');
}
};
