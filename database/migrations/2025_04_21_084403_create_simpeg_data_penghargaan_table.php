<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simpeg_data_penghargaan', function (Blueprint $table) {
             $table->bigIncrements('id');
            
            // Foreign key
            $table->integer('pegawai_id');
            
            // Data penghargaan
        

            $table->string('jenis_penghargaan', 100)->nullable();
            $table->string('nama_penghargaan', 255)->nullable();
            $table->string('no_sk', 100)->nullable();
              $table->date('tanggal_sk')->nullable();
                $table->string('keterangan', 255)->nullable();
            $table->date('tanggal_penghargaan')->nullable();  // Diperbaiki dari 'tanggai' ke 'tanggal'
            $table->date('file_penghargaan')->nullable();  
       
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_penghargaan');
    }
};