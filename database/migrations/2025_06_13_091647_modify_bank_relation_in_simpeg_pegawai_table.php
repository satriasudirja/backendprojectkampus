<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
 // dalam file migration yang baru dibuat
public function up()
{
    Schema::table('simpeg_pegawai', function (Blueprint $table) {
        // 1. Tambah kolom baru untuk foreign key
        $table->unsignedBigInteger('bank_id')->nullable()->after('nama_bank');

        // 2. Tambah relasi
        $table->foreign('bank_id')->references('id')->on('simpeg_bank')->onDelete('set null');

        // 3. (Opsional) Hapus kolom lama setelah data dimigrasikan
        // $table->dropColumn('nama_bank');
    });
}

public function down()
{
    Schema::table('simpeg_pegawai', function (Blueprint $table) {
        $table->dropForeign(['bank_id']);
        $table->dropColumn('bank_id');
        // $table->string('nama_bank', 100)->nullable(); // Kembalikan kolom jika perlu
    });
}

};