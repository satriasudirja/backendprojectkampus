<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGelarInGelarAkademikTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ubah tipe data kolom gelar menjadi varchar(10)
        Schema::table('gelar_akademik', function (Blueprint $table) {
            $table->string('gelar', 10)->change(); // Ubah panjang kolom menjadi 10
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kembalikan tipe data kolom gelar ke varchar(7)
        Schema::table('gelar_akademik', function (Blueprint $table) {
            $table->string('gelar', 7)->change(); // Kembalikan panjang kolom ke 7
        });
    }
}