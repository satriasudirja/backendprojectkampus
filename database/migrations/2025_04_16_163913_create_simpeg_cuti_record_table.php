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
        Schema::create('simpeg_cuti_record', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->integer('jenis_cuti_id');
            $table->string('no_urut_cuti', 50)->nullable();
            $table->date('tgl_mulai')->nullable();
             $table->date('tgl_diajukan')->nullable();
              $table->date('tgl_disetujui')->nullable();
               $table->date('tgl_ditolak')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->integer('jumlah_cuti')->nullable();
            $table->string('alasan_cuti', 255)->nullable();
            $table->string('alamat', 100)->nullable();
            $table->string('no_telp', 30)->nullable();
            $table->string('file_cuti')->nullable()->nullable();
            $table->string('status_pengajuan', 20);
            $table->timestamps();
            $table->softDeletes();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_cuti_record');
    }
};
