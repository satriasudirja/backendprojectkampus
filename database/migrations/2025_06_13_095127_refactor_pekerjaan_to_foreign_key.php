<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // dalam file migration yang baru
public function up()
{
    
    Schema::table('simpeg_data_riwayat_pekerjaan', function (Blueprint $table) {
        $table->uuid('pekerjaan_id')->nullable()->after('jenis_pekerjaan');
        $table->foreign('pekerjaan_id')->references('id')->on('simpeg_pekerjaan')->onDelete('set null');
        // $table->dropColumn('jenis_pekerjaan'); // Hapus setelah data lama dimigrasikan
    });
}

};


