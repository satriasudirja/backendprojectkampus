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
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->integer('setting_kehadiran_id')->nullable();
            $table->integer('jenis_kehadiran_id')->nullable();
            $table->integer('cuti_record_id')->nullable();
            $table->integer('izin_record_id')->nullable();
            $table->date('tanggal_absensi')->nullable();
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->boolean('terlambat')->nullable();
            $table->boolean('pulang_awal')->nullable();
            $table->string('file_foto',255);
            $table->string('check_sum_absensi', 255);
            $table->softDeletes();
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
