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
        Schema::table('simpeg_data_keluarga_pegawai', function (Blueprint $table) {
            // Cek apakah kolom sudah ada sebelum menambahkan
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'status_pengajuan')) {
                $table->enum('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditolak'])
                      ->default('draft')
                      ->after('keterangan');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'tgl_input')) {
                $table->date('tgl_input')->nullable()->after('status_pengajuan');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'tgl_diajukan')) {
                $table->timestamp('tgl_diajukan')->nullable()->after('tgl_input');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'tgl_disetujui')) {
                $table->timestamp('tgl_disetujui')->nullable()->after('tgl_diajukan');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'tgl_ditolak')) {
                $table->timestamp('tgl_ditolak')->nullable()->after('tgl_disetujui');
            }
            
            // Kolom untuk tracking siapa yang approve/reject (optional)
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('tgl_ditolak');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            }
            
            if (!Schema::hasColumn('simpeg_data_keluarga_pegawai', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            
            // Index untuk performa query
            $table->index(['pegawai_id', 'status_pengajuan'], 'idx_pegawai_status');
            $table->index(['status_pengajuan', 'tgl_diajukan'], 'idx_status_tgl_diajukan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_keluarga_pegawai', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_pegawai_status');
            $table->dropIndex('idx_status_tgl_diajukan');
            
            // Drop columns
            $table->dropColumn([
                'status_pengajuan',
                'tgl_input',
                'tgl_diajukan',
                'tgl_disetujui',
                'tgl_ditolak',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at'
            ]);
        });
    }
};