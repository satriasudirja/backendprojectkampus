<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataRiwayatPekerjaanTable extends Migration
{
    public function up()
    {
        Schema::create('data_riwayat_pekerjaan', function (Blueprint $table) {
            // Kolom utama
            $table->int('id')->primary();
            $table->int('pegawai_id');
            
            // Informasi pekerjaan
            $table->string('bidang_usaha', 200);
            $table->string('jenis_pekerjaan', 50);
            $table->string('jabatan', 50);
            $table->string('instansi', 100);
            $table->string('divisi', 100)->nullable();
            $table->text('deskripsi')->nullable();
            
            // Periode pekerjaan
            $table->date('mulai_bekerja');
            $table->date('selesai_bekerja')->nullable();
            
            // Status pekerjaan
            $table->boolean('area_pekerjaan')->default(false);
            
            // Informasi administrasi
            $table->date('tgl_input');
            
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
        Schema::dropIfExists('data_riwayat_pekerjaan');
    }
}