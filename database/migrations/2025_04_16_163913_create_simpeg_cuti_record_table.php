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
            $table->string('no_urut_cuti', 50);
            $table->date('tgl_mulai');
             $table->date('tgl_diajukan');
              $table->date('tgl_disetujui');
               $table->date('tgl_ditolak');
            $table->date('tgl_selesai');
            $table->integer('jumlah_cuti');
            $table->string('alasan_cuti', 255);
            $table->string('alamat', 100);
            $table->string('no_telp', 30);
            $table->string('file_cuti')->nullable();
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
