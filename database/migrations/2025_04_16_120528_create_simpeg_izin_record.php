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
        Schema::create('simpeg_izin_record', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->integer('jenis_izin_id');
            $table->string('alasan_izin', 255)->nullable();
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->integer('jumlah_izin')->nullable();
            $table->string('file_pendukung')->nullable();
            $table->string('status_pengajuan', 20);
            $table->string('no_izin')->nullable();
            $table->date('tgl_diajukan')->nullable();
            $table->date('tgl_disetujui')->nullable();
            $table->date('tgl_ditolak')->nullable();
            $table->date('approved_by')->nullable();
            $table->date('keterangan')->nullable();
            $table->timestamps();
        
            // Foreign key constraints
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');
                  
            // $table->foreign('jenis_izin_id')
            //       ->references('id')
            //       ->on('simpeg_jenis_izin')
            //       ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_izin_record');
    }
};