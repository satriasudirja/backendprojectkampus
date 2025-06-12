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
            // Diubah ke UUID karena model menggunakan string key
            $table->uuid('id')->primary(); 
            
            // Diperbaiki dari integer ke unsignedBigInteger untuk konsistensi
            $table->unsignedBigInteger('pegawai_id'); 
            
            // Kolom utama dengan perbaikan typo
            $table->string('jenis_kegiatan', 50);
            $table->string('status_pengajuan', 50)->default('draft');
            // $table->date('tanggal_pengajuan'); // REKOMENDASI: Dihapus dan diganti tgl_diajukan
            $table->string('sk_penugasan', 50)->nullable(); // Diperbaiki dari 'sk_pengasan'
            $table->string('perguruan_tinggi_afiliasi', 50)->nullable();
            $table->string('kelompok_bidang', 50)->nullable();
            $table->string('jenis_penelitian', 50)->nullable();
            $table->string('judul_penelitian', 255)->nullable(); // Diperbaiki dari 'juudl_penelitian' dan panjangnya
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->string('kategori_kegiatan', 255)->nullable();
            $table->string('jabatan_tugas', 50)->nullable();
            $table->string('lokasi_penugasan')->nullable();

            // --- KOLOM BARU UNTUK SISTEM PENGAJUAN ---
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            // ------------------------------------------

            // Menambahkan timestamps dan soft deletes (SANGAT DIREKOMENDASIKAN)
            $table->timestamps();
            $table->softDeletes();

            $table->index('pegawai_id');
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