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
        Schema::create('simpeg_evaluasi_kinerja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->integer('penilai_id');
            $table->integer('atasan_penilai_id');
              $table->string('jenis_kinerja');
            // $table->string('jenis_kinerja'); //(dosen, pegawai)
            $table->string('periode_tahun', 10);
            $table->date('tanggal_penilaian');
            $table->float('nilai_kehadiran')->nullable();
            $table->float('nilai_pendidikan')->nullable();
            $table->float('nilai_penelitian')->nullable();
            $table->float('nilai_pengabdian')->nullable();
            $table->float('nilai_penunjang1');
            $table->float('nilai_penunjang2');
            $table->float('nilai_penunjang3');
            $table->float('nilai_penunjang4');
            $table->float('total_nilai');
            $table->string('sebutan_total', 20);
            $table->date('tgl_input');
            $table->timestamps();
            $table->softDeletes();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_evaluasi_kinerja');
    }
};
