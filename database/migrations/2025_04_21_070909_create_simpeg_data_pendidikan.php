<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_data_pendidikan', function (Blueprint $table) {
            // Kolom utama
            $table->integer('id')->primary();
            $table->integer('pegawai_id'); // Diperbaiki dari 'pegawa1_id'
            
            // Informasi pengajuan
            $table->string('jenis_kegiatan', 50);
            $table->string('status_pengajuan', 50);
            $table->date('tanggal_pengajuan');
            $table->string('sk_penugasan', 50)->nullable(); // Diperbaiki dari 'sk_pengasan'
            
            // Informasi pendidikan
            $table->string('perguruan_tinggi_sasaran', 100)->nullable();
            $table->string('bidang_tugas', 100)->nullable();
            $table->integer('lama_kegiatan')->nullable();
            $table->string('nama_kegiatan', 100)->nullable();
            
            // Informasi bahan ajar
            $table->string('jenis_bahan_ajar', 100)->nullable();
            $table->string('judul_bahan_ajar', 200)->nullable();
            $table->string('penerbit', 50)->nullable();
            $table->string('penyelenggara', 100)->nullable(); // Diperbaiki dari 'penyelenggana'
            
            // Informasi tambahan
            $table->string('tugas_tambahan', 255)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->date('tanggal_pelaksanaan')->nullable();
            
            $table->timestamps();

            // Foreign key
            // $table->foreign('pegawai_id')
            //       ->references('id')
            //       ->on('pegawai')
            //       ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_data_pendidikan');
    }
};