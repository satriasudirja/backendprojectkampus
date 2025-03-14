<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNamaInMediaPublikasiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ubah tipe data kolom nama menjadi varchar(100)
        Schema::table('media_publikasi', function (Blueprint $table) {
            $table->string('nama', 200)->change(); // Ubah panjang kolom menjadi 100
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kembalikan tipe data kolom nama ke varchar(50)
        Schema::table('media_publikasi', function (Blueprint $table) {
            $table->string('nama', 50)->change(); // Kembalikan panjang kolom ke 50
        });
    }
}