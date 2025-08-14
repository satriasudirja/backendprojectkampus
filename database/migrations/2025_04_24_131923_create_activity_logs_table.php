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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // PERBAIKAN: Gunakan foreignUuid() untuk membuat kolom UUID 
            // sekaligus menjadikannya foreign key.
            $table->foreignUuid('pegawai_id')
                  ->nullable() // Jadikan kolom ini opsional (bisa NULL)
                  ->constrained('simpeg_pegawai') // Tambahkan constraint ke tabel simpeg_pegawai
                  ->onDelete('cascade'); // Aksi saat data pegawai dihapus

            $table->string('event'); // create, update, delete
            $table->string('model_type'); // Nama model yang diubah
            $table->uuid('model_id'); // ID data model-nya
            $table->json('changes')->nullable(); // Perubahan
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
