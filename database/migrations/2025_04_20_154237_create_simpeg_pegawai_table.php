<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('simpeg_pegawai', function (Blueprint $table) {
            $table->integer('id')->primary();
            
           // Kolom relasi (tanpa foreign key constraint)
           $table->integer('user_id')->nullable();
           $table->integer('unit_kerja_id')->nullable();
           $table->integer('kode_status_pernikahan')->nullable();
           $table->integer('status_aktif_id')->nullable();
           $table->integer('jabatan_akademik_id')->nullable();
           $table->integer('suku_id')->nullable();
            // Data pribadi
            $table->string('nama', 255);
            $table->string('nip', 20)->unique();
            $table->string('password', 100);
            $table->string('nidn', 20)->nullable();
            $table->string('gelar_depan', 20)->nullable();
            $table->string('gelar_belakang', 30)->nullable();
            $table->string('jenis_kelamin', 20);
            $table->string('tempat_lahir', 20);
            $table->date('tanggal_lahir');
            $table->string('nama_ibu_kandung', 50);
            
            // Data kepegawaian
            $table->string('no_sk_capeg', 50)->nullable();
            $table->date('tanggal_sk_capeg')->nullable();
            $table->string('golongan_capeg', 50)->nullable();
            $table->date('tmt_capeg')->nullable();
            $table->string('no_sk_pegawai', 50)->nullable();
            $table->date('tanggal_sk_pegawai')->nullable();
            
            // Alamat dan kontak
            $table->string('alamat_domisili', 255);
            $table->string('agama', 15);
            $table->string('golongan_darah', 5)->nullable();
            $table->string('kota', 30);
            $table->string('provinsi', 30);
            $table->string('kode_pos', 5);
            $table->string('no_telepon_domisili_kontak', 15)->nullable();
            $table->string('no_handphone', 15);
            $table->string('no_telephone_kantor', 15)->nullable();
            $table->string('no_kk', 16);
            $table->string('email_pribadi', 50);
            
            // Data tambahan (lanjutan)
            $table->string('no_ktp', 30);
            $table->float('jarak_rumah_domisili')->nullable();
            $table->string('npwp', 20)->nullable();
            $table->string('no_kartu_bpjs', 15)->nullable();
            $table->string('file_sertifikasi_dosen', 100)->nullable();
            $table->string('no_kartu_pensiun', 15)->nullable();
            $table->string('status_kerja', 50);
            $table->string('kepemilikan_nohp_utama', 50)->nullable();
            $table->string('alamat_kependudukan', 255)->nullable();
            
            // File dan dokumen
            $table->string('file_ktp', 100)->nullable();
            $table->string('file_kk', 100)->nullable();
            $table->string('no_rekening', 30)->nullable();
            $table->string('cabang_bank', 100)->nullable();
            $table->string('nama_bank', 100)->nullable();
            $table->string('file_rekening', 100)->nullable();
            $table->string('karpeg', 25)->nullable();
            $table->string('file_karpeg', 100)->nullable();
            $table->string('file_npwp', 100)->nullable();
            $table->string('file_bpjs', 100)->nullable();
            $table->string('file_bpjs_ketenagakerjaan', 100)->nullable();
            $table->string('no_bpjs', 16)->nullable();
            $table->string('no_bpjs_ketenagakerjaan', 16)->nullable();
            $table->string('no_bpjs_pensiun', 16)->nullable();
            $table->string('file_bpjs_pensiun', 100)->nullable();
            
            // Data fisik
            $table->integer('tinggi_badan')->nullable();
            $table->integer('berat_badan')->nullable();
            $table->string('file_tanda_tangan', 100)->nullable();
            
            // Audit trail
            $table->string('modified_by', 30)->nullable();
            $table->timestamp('modified_dt')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_pegawai');
    }
};