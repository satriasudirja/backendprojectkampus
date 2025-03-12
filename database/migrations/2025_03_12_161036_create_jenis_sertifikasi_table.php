<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisSertifikasiTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_sertifikasi', function (Blueprint $table) {
            $table->string('kode', 5)->primary();
            $table->string('jenis_sertifikasi', 100);
            $table->string('kategorisertifikasi', 100);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_sertifikasi');
    }
}