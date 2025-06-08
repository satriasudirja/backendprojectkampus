<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simpeg_data_diklat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            $table->string('jenis_diklat', 100);
            $table->string('kategori_diklat', 150);
            $table->string('tingkat_diklat', 50);
            $table->string('nama_diklat', 255);
            $table->string('penyelenggara', 100);
            $table->string('peran', 30);
            $table->integer('jumlah_jam');
            $table->string('no_sertifikat', 100);
            $table->date('tgl_sertifikat')->nullable();;
            $table->string('tahun_penyelenggaraan', 4);
            $table->string('tempat', 50);
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->string('sk_penugasan')->nullable();
            $table->date('tgl_input');
            $table->string('status_pengajuan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_diklat');
    }
};
