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
        Schema::create('simpeg_data_pendidikan_formal', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('pegawai_id');
            $table->integer('jenjang_studi');
            $table->integer('perguruan_tinggi_id');
            $table->integer('prodi_perguruan_tinggi_id');
            $table->integer('gelar_akademik_id');
            $table->boolean('lokasi_studi');
            $table->string('nama_institusi', 100);
            $table->string('nisn', 30);
            $table->string('konsentrasi', 100);
            $table->string('tahun_masuk', 4);
            $table->date('tanggal_kelulusan');
            $table->string('tahun_lulus', 4);
            $table->string('nomor_ijazah', 50);
            $table->date('tanggal_ijazah');
            $table->text('file_ijazah');
            $table->text('file_transkrip');
            $table->string('nomor_ijazah_negara', 50);
            $table->string('gelar_ijazah_negara', 30);
            $table->date('tgl_input');
            $table->string('nomor_induk', 30);
            $table->text('judul_tugas');
            $table->string('letak_gelar', 10);
            $table->integer('jumlah_semster_ditempuh');
            $table->integer('jumlah_sks_kelulusan');
            $table->float('ipk_kelulusan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_pendidikan_formal');
    }
};
