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
            $table->bigIncrements('id');
            $table->integer('pegawai_id')->unsigned();


            $table->string('lokasi_studi')->nullable();
          $table->integer('jenjang_pendidikan_id')->nullable();
           $table->integer('perguruan_tinggi_id')->nullable();
             $table->integer('prodi_perguruan_tinggi_id')->nullable();
            $table->integer('gelar_akademik_id')->nullable();

             
                
                 
            
            $table->string('bidang_studi', 100)->nullable();
            $table->string('nisn', 30)->nullable();
            $table->string('konsentrasi', 100)->nullable();
            $table->string('tahun_masuk', 4)->nullable();
            $table->date('tanggal_kelulusan')->nullable();
            $table->string('tahun_lulus', 4)->nullable();
            $table->string('nomor_ijazah', 50)->nullable();
            $table->date('tanggal_ijazah')->nullable();
            $table->text('file_ijazah')->nullable();
            $table->text('file_transkrip')->nullable();
            $table->string('nomor_ijazah_negara', 50)->nullable();
            $table->string('gelar_ijazah_negara', 30)->nullable();
            $table->date('tgl_input')->nullable();
             $table->date('tanggal_ijazah_negara')->nullable();
            $table->string('nomor_induk', 30)->nullable();
            $table->text('judul_tugas')->nullable();
            $table->string('letak_gelar', 10)->nullable();
            $table->integer('jumlah_semster_ditempuh')->nullable();
            $table->integer('jumlah_sks_kelulusan')->nullable();
            $table->float('ipk_kelulusan')->nullable();
             $table->string('status_pengajuan')->nullable();
               $table->date('tanggal_diajukan')->nullable();
                 $table->date('tanggal_disetujui')->nullable();
                  $table->string('dibuat_oleh')->nullable();
            $table->timestamps();
             $table->softDeletes();
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
