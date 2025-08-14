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
    public function up()
    {
        // Buat tabel simpeg_berita dari awal dengan struktur yang benar
        Schema::create('simpeg_berita', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('unit_kerja_id'); // Langsung gunakan TEXT untuk menyimpan array unit kerja
            $table->string('judul', 100);
            $table->text('konten')->nullable();
            $table->string('slug', 255)->unique();
            $table->date('tgl_posting');
            $table->date('tgl_expired')->nullable();
            $table->boolean('prioritas')->default(false);
            $table->string('gambar_berita', 255)->nullable();
            $table->string('file_berita', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Buat tabel pivot untuk hubungan many-to-many dengan jabatan akademik
        Schema::create('simpeg_berita_jabatan_akademik', function (Blueprint $table) {
            // Definisikan kolom terlebih dahulu
            $table->uuid('id')->primary(); // Tambahkan ->primary() agar konsisten
            $table->uuid('berita_id');
            $table->uuid('jabatan_akademik_id');
            $table->timestamps();

            // Buat indeks untuk performa query.
            // Walaupun foreign key secara otomatis membuat indeks di beberapa DB,
            // mendefinisikannya secara eksplisit adalah praktik yang baik.
            $table->index('berita_id');
            $table->index('jabatan_akademik_id');
            
            // Buat unique constraint untuk mencegah duplikasi
            $table->unique(['berita_id', 'jabatan_akademik_id'], 'berita_jabatan_unique');
            
            // PERBAIKAN: Gunakan metode foreign() untuk menambahkan constraint ke kolom yang sudah ada.
            // Ini akan menghindari error "Duplicate column".
            $table->foreign('berita_id', 'fk_berita_jabatan_berita_id')
                  ->references('id')
                  ->on('simpeg_berita')
                  ->onDelete('cascade');
                  
            $table->foreign('jabatan_akademik_id', 'fk_berita_jabatan_akademik_id')
                  ->references('id')
                  ->on('simpeg_jabatan_akademik')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Urutan drop harus dibalik dari pembuatan untuk menjaga integritas referensial
        Schema::dropIfExists('simpeg_berita_jabatan_akademik');
        Schema::dropIfExists('simpeg_berita');
    }
};
