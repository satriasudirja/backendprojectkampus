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
        Schema::table('simpeg_data_pendidikan_formal', function (Blueprint $table) {
            // Tambahkan kolom nama_institusi setelah lokasi_studi
       
                // Untuk PostgreSQL atau driver lain yang tidak mendukung after()
                $table->string('nama_institusi', 100)->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_pendidikan_formal', function (Blueprint $table) {
            $table->dropColumn('nama_institusi');
        });
    }
};