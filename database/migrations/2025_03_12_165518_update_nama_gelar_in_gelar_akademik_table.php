<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNamaGelarInGelarAkademikTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ubah tipe data kolom nama_gelar menjadi varchar(100)
        Schema::table('gelar_akademik', function (Blueprint $table) {
            $table->string('nama_gelar', 100)->change(); // Ubah panjang kolom menjadi 100
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kembalikan tipe data kolom nama_gelar ke varchar(20)
        Schema::table('gelar_akademik', function (Blueprint $table) {
            $table->string('nama_gelar', 20)->change(); // Kembalikan panjang kolom ke 20
        });
    }
}