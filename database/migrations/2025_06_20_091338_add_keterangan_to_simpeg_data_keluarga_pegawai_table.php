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
    $table->text('keterangan')->nullable(); // Atau string(255) jika tidak terlalu panjang
    // Jika keterangan wajib, hapus ->nullable()
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_keluarga_pegawai', function (Blueprint $table) {
    $table->dropColumn('keterangan');
});
    }
};
