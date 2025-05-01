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
        // Cek apakah kolom sudah ada sebelum menambahkannya
        if (Schema::hasTable('simpeg_master_perguruan_tinggi')) {
            Schema::table('simpeg_master_perguruan_tinggi', function (Blueprint $table) {
                // Tambahkan kolom yang hilang jika belum ada
                if (!Schema::hasColumn('simpeg_master_perguruan_tinggi', 'akreditasi')) {
                    $table->string('akreditasi', 5)->nullable();
                }
                
                if (!Schema::hasColumn('simpeg_master_perguruan_tinggi', 'email')) {
                    $table->string('email', 50)->nullable();
                }
                
                if (!Schema::hasColumn('simpeg_master_perguruan_tinggi', 'website')) {
                    $table->string('website', 100)->nullable();
                }
                
                if (!Schema::hasColumn('simpeg_master_perguruan_tinggi', 'is_aktif')) {
                    $table->boolean('is_aktif')->default(true);
                }
                
                // Tambahkan soft deletes jika belum ada
                if (!Schema::hasColumn('simpeg_master_perguruan_tinggi', 'deleted_at')) {
                    $table->softDeletes();
                }
                
                // Tambahkan indeks jika belum ada
                if (!Schema::hasIndex('simpeg_master_perguruan_tinggi', 'simpeg_master_perguruan_tinggi_kode_index')) {
                    $table->index('kode');
                }
                
                if (!Schema::hasIndex('simpeg_master_perguruan_tinggi', 'simpeg_master_perguruan_tinggi_nama_universitas_index')) {
                    $table->index('nama_universitas');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop kolom yang ditambahkan jika diperlukan
        Schema::table('simpeg_master_perguruan_tinggi', function (Blueprint $table) {
            // Hati-hati saat menghapus kolom, pastikan tidak ada data penting
            if (Schema::hasColumn('simpeg_master_perguruan_tinggi', 'akreditasi')) {
                $table->dropColumn('akreditasi');
            }
            
            if (Schema::hasColumn('simpeg_master_perguruan_tinggi', 'email')) {
                $table->dropColumn('email');
            }
            
            if (Schema::hasColumn('simpeg_master_perguruan_tinggi', 'website')) {
                $table->dropColumn('website');
            }
            
            if (Schema::hasColumn('simpeg_master_perguruan_tinggi', 'is_aktif')) {
                $table->dropColumn('is_aktif');
            }
            
            if (Schema::hasColumn('simpeg_master_perguruan_tinggi', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            
            // Drop indeks jika ada
            if (Schema::hasIndex('simpeg_master_perguruan_tinggi', 'simpeg_master_perguruan_tinggi_kode_index')) {
                $table->dropIndex(['kode']);
            }
            
            if (Schema::hasIndex('simpeg_master_perguruan_tinggi', 'simpeg_master_perguruan_tinggi_nama_universitas_index')) {
                $table->dropIndex(['nama_universitas']);
            }
        });
    }
};