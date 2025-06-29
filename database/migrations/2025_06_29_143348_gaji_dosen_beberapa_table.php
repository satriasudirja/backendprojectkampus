<?php
// database/migrations/YYYY_MM_DD_HHMMSS_create_payroll_tables.php

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
        // Tabel untuk mengelola periode penggajian
        Schema::create('penggajian_periode', function (Blueprint $table) {
            $table->id();
            $table->year('tahun');
            $table->unsignedTinyInteger('bulan'); // 1-12
            $table->string('nama_periode'); // Contoh: "Penggajian Juni 2025"
            $table->enum('status', ['draft', 'processing', 'completed', 'failed'])->default('draft');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['tahun', 'bulan']); // Hanya boleh ada 1 periode per bulan/tahun
        });

        // Tabel untuk "slip gaji" setiap pegawai per periode
        Schema::create('penggajian_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('penggajian_periode')->onDelete('cascade');
            // FIX: Mengubah foreignUuid menjadi foreignId agar cocok dengan tipe BIGINT dari tabel simpeg_pegawai
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_potongan', 15, 2)->default(0);
            $table->decimal('gaji_bersih', 15, 2)->default(0); // take-home pay
            $table->timestamps();
            $table->unique(['periode_id', 'pegawai_id']);
        });

        // Tabel untuk rincian semua komponen pendapatan
        Schema::create('penggajian_komponen_pendapatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penggajian_pegawai_id')->constrained('penggajian_pegawai')->onDelete('cascade');
            $table->string('kode_komponen'); // Contoh: 'GAPOK', 'TUNJ_STRUKTURAL', 'THR'
            $table->string('deskripsi'); // Contoh: "Gaji Pokok Golongan III/a", "Tunjangan Jabatan Struktural", "Tunjangan Hari Raya"
            $table->decimal('nominal', 15, 2);
            $table->timestamps();
        });

        // Tabel untuk rincian semua komponen potongan
        Schema::create('penggajian_komponen_potongan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penggajian_pegawai_id')->constrained('penggajian_pegawai')->onDelete('cascade');
            $table->string('kode_komponen'); // Contoh: 'PPH21', 'BPJS_KES'
            $table->string('deskripsi'); // Contoh: "Pajak PPh 21", "Potongan BPJS Kesehatan"
            $table->decimal('nominal', 15, 2);
            $table->timestamps();
        });

        // Tabel untuk mengelola tunjangan yang bersifat insidentil / tidak tetap
        Schema::create('master_tunjangan_tambahan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_tunjangan')->unique();
            $table->string('nama_tunjangan');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabel untuk mengelola jenis-jenis potongan
        Schema::create('master_potongan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_potongan')->unique();
            $table->string('nama_potongan');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penggajian_komponen_potongan');
        Schema::dropIfExists('penggajian_komponen_pendapatan');
        Schema::dropIfExists('penggajian_pegawai');
        Schema::dropIfExists('penggajian_periode');
        Schema::dropIfExists('master_tunjangan_tambahan');
        Schema::dropIfExists('master_potongan');
    }
};
