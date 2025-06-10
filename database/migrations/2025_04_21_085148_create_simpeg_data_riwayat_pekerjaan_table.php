<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_data_riwayat_pekerjaan', function (Blueprint $table) {
            // Kolom utama
            $table->bigIncrements('id');
            $table->integer('pegawai_id');
            
            // Informasi pekerjaan
            $table->string('bidang_usaha', 200);
            $table->string('jenis_pekerjaan', 50);
            $table->string('jabatan', 50);
            $table->string('instansi', 100);
            $table->string('divisi', 100)->nullable();
            $table->text('deskripsi')->nullable();
             $table->string('status_pengajuan', 50)->nullable()->after('area_pekerjaan');

            // Periode pekerjaan
            $table->date('mulai_bekerja');
            $table->date('selesai_bekerja')->nullable();
            
            // Status pekerjaan
            $table->boolean('area_pekerjaan')->default(false);
            
            // Informasi administrasi
            $table->date('tgl_input');
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            
            $table->timestamps();


        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_data_riwayat_pekerjaan');
        
    }
};