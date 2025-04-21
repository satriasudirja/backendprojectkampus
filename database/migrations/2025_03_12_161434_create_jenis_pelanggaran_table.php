<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenisPelanggaranTable extends Migration
{
    public function up()
    {
        Schema::create('jenis_pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5);
            $table->string('nama_pelanggaran', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_pelanggaran');
    }
}