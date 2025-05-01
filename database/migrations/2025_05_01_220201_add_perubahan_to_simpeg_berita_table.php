<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Langkah 1: Modifikasi tabel simpeg_berita untuk mengubah unit_kerja_id menjadi JSON
        if (Schema::hasTable('simpeg_berita')) {
            Schema::table('simpeg_berita', function (Blueprint $table) {
                // Ubah tipe data unit_kerja_id menjadi TEXT untuk PostgreSQL jika belum
                if (Schema::hasColumn('simpeg_berita', 'unit_kerja_id')) {
                    // Check if column is already TEXT
                    $columnType = DB::select("
                        SELECT data_type 
                        FROM information_schema.columns 
                        WHERE table_name = 'simpeg_berita' 
                        AND column_name = 'unit_kerja_id'
                    ")[0]->data_type;
                    
                    if ($columnType !== 'text') {
                        DB::statement('ALTER TABLE simpeg_berita ALTER COLUMN unit_kerja_id TYPE TEXT USING unit_kerja_id::TEXT');
                    }
                }
                
                // Hapus indeks pada unit_kerja_id dengan pendekatan yang lebih aman
                try {
                    $indexExists = DB::select("
                        SELECT indexname 
                        FROM pg_indexes 
                        WHERE tablename = 'simpeg_berita' 
                        AND indexname = 'simpeg_berita_unit_kerja_id_index'
                    ");
                    
                    if (!empty($indexExists)) {
                        DB::statement('DROP INDEX IF EXISTS simpeg_berita_unit_kerja_id_index');
                    }
                } catch (\Exception $e) {
                    // Jika query gagal, coba menggunakan metode Blueprint
                    try {
                        $table->dropIndex(['unit_kerja_id']);
                    } catch (\Exception $innerE) {
                        // Indeks mungkin tidak ada, lewati
                    }
                }
                
                // Hapus kolom jabatan_akademik_id jika ada
                if (Schema::hasColumn('simpeg_berita', 'jabatan_akademik_id')) {
                    $table->dropColumn('jabatan_akademik_id');
                }
            });
        }

        // Langkah 2: Buat tabel pivot jika belum ada
        if (!Schema::hasTable('simpeg_berita_jabatan_akademik')) {
            Schema::create('simpeg_berita_jabatan_akademik', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('berita_id');
                $table->unsignedBigInteger('jabatan_akademik_id');
                $table->timestamps();

                // Buat indeks untuk performa query
                $table->index('berita_id');
                $table->index('jabatan_akademik_id');
                
                // Buat unique constraint untuk mencegah duplikasi
                $table->unique(['berita_id', 'jabatan_akademik_id'], 'berita_jabatan_unique');
                
                // Tambahkan foreign key constraints dengan cascade delete
                $table->foreign('berita_id', 'fk_berita_jabatan_berita_id')
                      ->references('id')
                      ->on('simpeg_berita')
                      ->onDelete('cascade');
                      
                $table->foreign('jabatan_akademik_id', 'fk_berita_jabatan_akademik_id')
                      ->references('id')
                      ->on('simpeg_jabatan_akademik')
                      ->onDelete('cascade');
            });
        } else {
            // Tabel sudah ada, periksa apakah indeks sudah ada
            Schema::table('simpeg_berita_jabatan_akademik', function (Blueprint $table) {
                // Periksa indeks dengan SQL langsung
                try {
                    // Periksa indeks berita_id
                    $beritaIdIndex = DB::select("
                        SELECT indexname 
                        FROM pg_indexes 
                        WHERE tablename = 'simpeg_berita_jabatan_akademik' 
                        AND indexname = 'simpeg_berita_jabatan_akademik_berita_id_index'
                    ");
                    
                    if (empty($beritaIdIndex)) {
                        $table->index('berita_id');
                    }
                    
                    // Periksa indeks jabatan_akademik_id
                    $jabatanIdIndex = DB::select("
                        SELECT indexname 
                        FROM pg_indexes 
                        WHERE tablename = 'simpeg_berita_jabatan_akademik' 
                        AND indexname = 'simpeg_berita_jabatan_akademik_jabatan_akademik_id_index'
                    ");
                    
                    if (empty($jabatanIdIndex)) {
                        $table->index('jabatan_akademik_id');
                    }
                    
                    // Periksa unique constraint
                    $uniqueIndex = DB::select("
                        SELECT indexname 
                        FROM pg_indexes 
                        WHERE tablename = 'simpeg_berita_jabatan_akademik' 
                        AND indexname = 'berita_jabatan_unique'
                    ");
                    
                    if (empty($uniqueIndex)) {
                        try {
                            $table->unique(['berita_id', 'jabatan_akademik_id'], 'berita_jabatan_unique');
                        } catch (\Exception $e) {
                            // Constraint mungkin sudah ada dengan nama berbeda
                        }
                    }
                } catch (\Exception $e) {
                    // Jika terjadi kesalahan, coba dengan try-catch per operasi
                    try {
                        $table->index('berita_id');
                    } catch (\Exception $innerE) {}
                    
                    try {
                        $table->index('jabatan_akademik_id');
                    } catch (\Exception $innerE) {}
                    
                    try {
                        $table->unique(['berita_id', 'jabatan_akademik_id'], 'berita_jabatan_unique');
                    } catch (\Exception $innerE) {}
                }
                
                // Foreign key checks lebih kompleks, kita skip untuk keamanan
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Tidak perlu melakukan apa-apa dalam down method karena
        // kita hanya ingin memastikan struktur tabel yang benar,
        // bukan menghapus tabel yang mungkin sudah berisi data
    }
};