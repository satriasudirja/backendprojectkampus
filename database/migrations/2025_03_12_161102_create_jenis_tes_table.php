<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisTesTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_tes', function (Blueprint $table) {
            $table->string('kode', 4)->primary();
            $table->string('jenis_tes', 25);
            $table->double('nilai_minimal', 10, 5)->nullable(); // Boleh null biar sesuai sama seeder
            $table->double('nilai_maksimal', 10, 5)->nullable(); // Kalau ini juga mau null bisa dikasih nullable()
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_tes');
    }
}