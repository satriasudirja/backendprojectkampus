// database/migrations/[timestamp]_create_simpeg_data_sertifikasi_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('simpeg_data_sertifikasi', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('pegawai_id')->nullable();           
            $table->integer('jenis_sertifikasi_id')->nullable();  
            $table->integer('bidang_ilmu_id')->nullable();        
            $table->string('no_sertifikasi', 50);
            $table->date('tgl_sertifikasi');
            $table->string('no_registrasi', 20);
            $table->string('no_peserta', 50);
            $table->string('peran', 100);
            $table->string('penyelenggara', 100);
            $table->string('tempat', 100);
            $table->string('lingkup', 20);
            $table->date('tgl_input');
            $table->timestamps();
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_data_sertifikasi');
    }
};