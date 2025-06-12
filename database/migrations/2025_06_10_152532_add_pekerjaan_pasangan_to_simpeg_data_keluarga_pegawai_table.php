<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Menggunakan Schema::table untuk mengubah tabel yang sudah ada
        Schema::table('simpeg_data_keluarga_pegawai', function (Blueprint $table) {
            // Menambahkan kolom baru setelah kolom 'pasangan_berkerja_dalam_satu_instansi'
            // agar letaknya rapi dan berkelompok dengan data pasangan lainnya.

            $table->string('jenis_pekerjaan_pasangan', 100)->nullable()->after('pasangan_berkerja_dalam_satu_instansi');
            $table->string('status_kepegawaian_pasangan', 100)->nullable()->after('jenis_pekerjaan_pasangan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Kode ini akan dijalankan jika Anda melakukan rollback migrasi
        Schema::table('simpeg_data_keluarga_pegawai', function (Blueprint $table) {
            $table->dropColumn(['jenis_pekerjaan_pasangan', 'status_kepegawaian_pasangan']);
        });
    }
};