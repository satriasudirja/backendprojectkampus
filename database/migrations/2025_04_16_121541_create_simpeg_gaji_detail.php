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
        Schema::create('simpeg_gaji_detail', function (Blueprint $table) {
            $table->bigIncrements('id'); // Diubah dari FK ke PK karena seharusnya ini tabel detail
            $table->integer('gaji_slip_id'); // Diperbaiki dari 'gaj1_slip_id' ke 'gaji_slip_id'
            $table->integer('komponen_id');
            $table->float('jumlah', 12, 2); // float4 equivalent
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // // Foreign key constraints
            // $table->foreign('gaji_slip_id')
            //       ->references('id')
            //       ->on('simpeg_gaji_slip') // Asumsi nama tabel slip gaji
            //       ->onDelete('cascade');
                  
            // $table->foreign('komponen_id')
            //       ->references('id')
            //       ->on('simpeg_komponen_gaji') // Asumsi nama tabel komponen gaji
            //       ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_detail');
    }
};
