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
    Schema::create('jamkerja', function (Blueprint $table) {
        $table->id(); // id INT primary key
        $table->string('jenis_jam_kerja', 30); // Jenis Jam Kerja varchar(30)
        $table->boolean('jam_normal'); // Jam Normal Boolean
        $table->time('jam_datang'); // Jam Datang time
        $table->time('jam_pulang'); // Jam Pulang time
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('jamkerja');
}
};
