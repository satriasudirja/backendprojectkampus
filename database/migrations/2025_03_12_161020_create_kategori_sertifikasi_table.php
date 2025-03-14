<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKategoriSertifikasiTable extends Migration
{
    public function up()
    {
        Schema::create('kategori_sertifikasi', function (Blueprint $table) {
            $table->id();
            $table->string('kategori_sertifikasi', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('kategori_sertifikasi');
    }
}