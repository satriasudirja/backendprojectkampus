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
        Schema::table('simpeg_data_penghargaan', function (Blueprint $table) {
            // Tambahkan foreign key untuk pegawai_id
            $table->foreign('pegawai_id')
                ->references('id')->on('simpeg_pegawai')
                ->onDelete('cascade');

            $table->foreign('jenis_penghargaan_id')
            ->references('id')->on('simpeg_jenis_penghargaan')
            ->onDelete('cascade');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_data_penghargaan', function (Blueprint $table) {
            // Hapus foreign key pegawai_id
            $table->dropForeign(['pegawai_id']);
            $table->dropForeign(['jenis_penghargaan_id']);
            $table->dropColumn('jenis_penghargaan_id');
        });
    }
};