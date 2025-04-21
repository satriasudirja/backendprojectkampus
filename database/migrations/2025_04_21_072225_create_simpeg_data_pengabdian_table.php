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
        Schema::create('simpeg_data_pengabdian', function (Blueprint $table) {
            $table->int('id')->primary();
            $table->int('pegawai_id');
            $table->string('jenis_kegiatan', 50);
            $table->string('status_pengajuan', 50);
            $table->date('tanggal_pengajuan');
            $table->string('sk_pengasan', 50);
            $table->string('perguruan_tinggi_afiliasi', 50)->nullable();
            $table->string('kelompok_bidang', 50)->nullable();
            $table->string('jenis_penelitian', 50)->nullable();
            $table->string('juudl_penelitian', 50)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->string('kategori_kegiatan', 255)->nullable();
            $table->string('jabatan_tugas', 50)->nullable();
            $table->string('lokasi_penugasan')->nullable();

            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('simpeg_pegawai')
            //       ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_pengabdian');
    }
};