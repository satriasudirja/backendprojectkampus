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
        Schema::table('penggajian_periode', function (Blueprint $table) {
            // Ubah 'tahun' dari integer menjadi string (varchar)
            // Tentukan panjangnya, misal 4 karakter untuk '2025'
            $table->string('tahun', 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penggajian_periode', function (Blueprint $table) {
            // Kode untuk membatalkan (rollback)
            // Ubah kembali 'tahun' menjadi integer
            $table->integer('tahun')->change();
        });
    }
};