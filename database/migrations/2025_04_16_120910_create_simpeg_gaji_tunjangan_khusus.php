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
        Schema::create('simpeg_gaji_tunjangan_khusus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->integer('komponen_id');
            $table->float('jumlah', 8, 2); // float4 equivalent with 8 digits total and 2 decimal places
            $table->date('tgl_mulai');
            $table->date('tgl_selesai')->nullable(); // Made nullable for ongoing allowances
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // Foreign key constraints
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');
                  
            // $table->foreign('komponen_id')
            //       ->references('id')
            //       ->on('simpeg_komponen_gaji')
            //       ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_tunjangan_khusus');
    }
};
