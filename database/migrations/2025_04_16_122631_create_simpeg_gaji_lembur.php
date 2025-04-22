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
        Schema::create('simpeg_gaji_lembur', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->float('durasi', 8, 2); // Durasi dalam jam (contoh: 1.5 jam)
            $table->decimal('upah_perjam', 12, 2);
            $table->decimal('total_upah', 12, 2); // Diperbaiki dari 'total_upal' ke 'total_upah'
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, paid
            $table->timestamps();

            // // Foreign key
            // $table->foreign('pegawai_id')
            //     ->references('id')
            //     ->on('simpeg_pegawai')
            //     ->onDelete('cascade');

            // // Indexes
            // $table->index(['pegawai_id', 'tanggal']);
            // $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_lembur');
    }
};