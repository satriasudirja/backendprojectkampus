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
        Schema::table('simpeg_data_hubungan_kerja', function (Blueprint $table) {
            // Tambah kolom is_aktif jika belum ada
            if (!Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
                $table->boolean('is_aktif')->default(false)->after('status_aktif_id');
            }
            
            // Tambah kolom status_pengajuan jika belum ada
            if (!Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
                $table->enum('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditolak'])
                      ->default('draft')
                      ->after('is_aktif');
            }
            
            // Tambah index untuk performance
            $table->index(['pegawai_id', 'is_aktif']);
            $table->index(['status_pengajuan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_hubungan_kerja', function (Blueprint $table) {
            // Drop index terlebih dahulu
            $table->dropIndex(['pegawai_id', 'is_aktif']);
            $table->dropIndex(['status_pengajuan']);
            
            // Drop kolom
            $table->dropColumn(['is_aktif', 'status_pengajuan']);
        });
    }
};