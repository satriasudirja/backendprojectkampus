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
        Schema::table('simpeg_data_pangkat', function (Blueprint $table) {
            //
                    // Foreign keys
            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('simpeg_pegawai')
                  ->onDelete('cascade');
                  
            $table->foreign('jenis_sk_id')
                  ->references('id')
                  ->on('simpeg_daftar_jenis_sk')
                  ->onDelete('restrict');
                  
            $table->foreign('jenis_kenaikan_pangkat_id')
                  ->references('id')
                  ->on('simpeg_jenis_kenaikan_pangkat')
                  ->onDelete('restrict');
                  
            $table->foreign('pangkat_id')
                  ->references('id')
                  ->on('simpeg_master_pangkat')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_pangkat', function (Blueprint $table) {
            //
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_sk_id']);
            $table->dropForeign(['jenis_kepangkatan_id']);
            $table->dropForeign(['pangkat_id']);
        });
    }
};
