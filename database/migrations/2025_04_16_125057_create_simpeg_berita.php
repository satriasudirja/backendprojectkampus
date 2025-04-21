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
        Schema::create('simpeg_berita', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('unit_kerja_id'); // Diperbaiki dari 'unit_kenja_id' ke 'unit_kerja_id'
            $table->string('judul', 100);
            $table->text('konten')->nullable(); // Ditambahkan untuk konten berita
            $table->string('slug', 255)->unique(); // Ditambahkan untuk URL SEO-friendly
            $table->date('tgl_posting');
            $table->date('tgl_expired')->nullable();
            $table->string('gambar_featured', 255)->nullable(); // Ditambahkan untuk gambar utama
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->timestamps();

            // Foreign key
            // $table->foreign('unit_kerja_id')
            //       ->references('id')
            //       ->on('simpeg_unit_kerja')
            //       ->onDelete('cascade');

            // Indexes
            $table->index('unit_kerja_id');
            $table->index('tgl_posting');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_berita');
    }
};