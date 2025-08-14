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
        Schema::create('simpeg_gaji_tunjangan_khusus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // PASTIKAN KEDUA KOLOM INI MENGGUNAKAN TIPE ->uuid()
            $table->uuid('pegawai_id');
            $table->uuid('komponen_id');
            
            // Di PostgreSQL, float() lebih baik diganti dengan decimal() untuk presisi keuangan
            $table->decimal('jumlah', 15, 2); 
            $table->date('tgl_mulai');
            $table->date('tgl_selesai')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_tunjangan_khusus');
    }
};
