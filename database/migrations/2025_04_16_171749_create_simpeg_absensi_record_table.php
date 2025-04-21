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
        Schema::create('simpeg_absensi_record', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('pegawai_id');
            $table->integer('setting_kehadiran_id');
            $table->integer('jenis_kehadiran_id');
            $table->date('tanggal_absensi');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->boolean('terlambat');
            $table->boolean('pulang_awal');
            $table->string('check_sum_absensi', 255);
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_absensi_record');
    }
};
